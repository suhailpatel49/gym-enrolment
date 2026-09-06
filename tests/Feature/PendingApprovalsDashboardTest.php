<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Widgets\EnrollmentStats;
use App\Filament\Widgets\OverdueBalances;
use App\Filament\Widgets\PendingApprovals;
use App\Filament\Widgets\RenewalsDue;
use App\Mail\EnrollmentConfirmation;
use App\Models\Enrollment;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PendingApprovalsDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** @return array<int, array{string}> */
    public static function roles(): array
    {
        return [['admin'], ['staff']];
    }

    #[DataProvider('roles')]
    public function test_dashboard_discovers_pending_reviews_before_operational_tables(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get('/admin')->assertOk()->assertSee('pending review');
        $widgets = array_values(app(Dashboard::class)->getWidgets());
        $this->assertContains(PendingApprovals::class, $widgets);
        $this->assertLessThan(array_search(OverdueBalances::class, $widgets), array_search(PendingApprovals::class, $widgets));
        $this->assertLessThan(array_search(RenewalsDue::class, $widgets), array_search(PendingApprovals::class, $widgets));
    }

    #[DataProvider('roles')]
    public function test_pending_stat_counts_only_pending_records_and_links_to_enrollments(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        Enrollment::factory()->count(7)->create(['approval_status' => 'pending']);
        Enrollment::factory()->count(2)->create();
        $widget = Livewire::test(EnrollmentStats::class)->assertSee('Pending approvals');
        $stat = collect($widget->instance()->getSchema('content')->getComponents()[0]->getChildSchema()->getComponents())
            ->first(fn (Stat $stat): bool => $stat->getLabel() === 'Pending approvals');
        $this->assertNotNull($stat);
        $this->assertSame('7', (string) $stat->getValue());
        $this->assertSame('warning', $stat->getColor());
        $this->assertSame(EnrollmentResource::getUrl('index'), $stat->getUrl());
    }

    #[DataProvider('roles')]
    public function test_pending_table_shows_newest_five_and_excludes_approved_records(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $pending = Enrollment::factory()->count(6)->sequence(fn (Sequence $sequence): array => [
            'created_at' => now()->subMinutes($sequence->index),
        ])->create(['approval_status' => 'pending']);
        $approved = Enrollment::factory()->create();
        $widget = Livewire::test(PendingApprovals::class)
            ->assertCanSeeTableRecords($pending->take(5), inOrder: true)
            ->assertCanNotSeeTableRecords([$pending->last(), $approved])
            ->assertCountTableRecords(6)
            ->assertCanRenderTableColumn('reference_code')
            ->assertCanRenderTableColumn('full_name')
            ->assertCanRenderTableColumn('membership_package')
            ->assertCanRenderTableColumn('created_at')
            ->assertTableActionVisible('view', $pending->first())
            ->assertTableActionHasUrl('view', EnrollmentResource::getUrl('view', ['record' => $pending->first()]), $pending->first())
            ->assertTableActionVisible('approveEnrollment', $pending->first());
        $this->assertSame('full', $widget->instance()->getColumnSpan());
    }

    #[DataProvider('roles')]
    public function test_approval_removes_the_row_and_preserves_one_confirmation_and_audit(string $role): void
    {
        Mail::fake();
        $actor = User::factory()->create(['role' => $role]);
        $pending = Enrollment::factory()->create(['approval_status' => 'pending']);
        Livewire::actingAs($actor)->test(PendingApprovals::class)
            ->callTableAction('approveEnrollment', $pending)
            ->assertNotified('Enrollment approved. The member confirmation email was queued.')
            ->call('$refresh')
            ->assertCanNotSeeTableRecords([$pending])
            ->assertSee('No pending approvals');
        $this->assertSame('approved', $pending->refresh()->approval_status);
        $this->assertSame($actor->id, $pending->approved_by);
        $approvedAt = $pending->approved_at;
        $this->assertNotNull($approvedAt);
        $this->assertFalse($pending->approve($actor));
        $this->assertSame($actor->id, $pending->approved_by);
        $this->assertTrue($approvedAt->equalTo($pending->approved_at));
        Mail::assertQueued(EnrollmentConfirmation::class, 1);
    }

    public function test_staff_cannot_see_private_contact_or_payment_data(): void
    {
        $pending = Enrollment::factory()->create([
            'approval_status' => 'pending',
            'email' => 'private-member@example.com',
            'mobile_number' => '9999988888',
            'address' => 'Private home address',
            'emergency_contact' => '7777766666',
            'payment_mode' => 'Private bank transfer',
            'amount_paid' => 12345,
            'has_balance' => true,
            'remaining_balance' => 6789,
        ]);
        $widget = Livewire::actingAs(User::factory()->staff()->create())->test(PendingApprovals::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertTableColumnHidden('email')
            ->assertTableColumnHidden('mobile_number');
        foreach (['private-member@example.com', '9999988888', 'Private home address', '7777766666', 'Private bank transfer', '12,345.00', '6,789.00'] as $privateValue) {
            $widget->assertDontSee($privateValue);
        }
        Livewire::actingAs(User::factory()->create())->test(PendingApprovals::class)
            ->assertSee('private-member@example.com')
            ->assertSee('9999988888');
    }
}
