<?php

namespace Tests\Feature;

use App\Filament\Resources\Enrollments\Actions\ApproveEnrollmentAction;
use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Resources\Enrollments\Pages\ViewEnrollment;
use App\Mail\EnrollmentConfirmation;
use App\Models\Enrollment;
use App\Models\User;
use Filament\Actions\Enums\ActionStatus;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnrollmentApprovalUiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** @return array<int, array{string, bool}> */
    public static function approvalActions(): array
    {
        return [['admin', false], ['staff', false], ['admin', true], ['staff', true]];
    }

    #[DataProvider('approvalActions')]
    public function test_pending_enrollments_can_be_approved_from_the_list_and_view(string $role, bool $table): void
    {
        Mail::fake();
        $actor = User::factory()->create(['role' => $role]);
        $record = Enrollment::factory()->create(['approval_status' => 'pending']);
        $page = Livewire::actingAs($actor)->test($table ? ListEnrollments::class : ViewEnrollment::class,
            $table ? [] : ['record' => $record->getRouteKey()]);

        if ($table) {
            $page->assertTableActionVisible('approveEnrollment', $record)
                ->callTableAction('approveEnrollment', $record);
        } else {
            $page->assertActionVisible('approveEnrollment')
                ->mountAction('approveEnrollment');
            $this->assertStringContainsString('Approval activates membership and emails confirmation to the member.', $page->getMountedActionModalHtml());
            $page->callMountedAction();
        }

        $page->assertNotified('Enrollment approved. The member confirmation email was queued.');
        $this->assertSame('approved', $record->refresh()->approval_status);
        $this->assertSame($actor->id, $record->approved_by);
        Mail::assertQueued(EnrollmentConfirmation::class, 1);
        if ($table) {
            $page->assertTableActionHidden('approveEnrollment', $record);
        } else {
            $page->assertActionHidden('approveEnrollment');
        }
    }

    public function test_list_shows_status_badges_and_filters_pending_and_approved_records(): void
    {
        $pending = Enrollment::factory()->create(['approval_status' => 'pending']);
        $approved = Enrollment::factory()->create();
        Livewire::actingAs(User::factory()->staff()->create())->test(ListEnrollments::class)
            ->assertCanSeeTableRecords([$pending, $approved])
            ->assertTableColumnExists('approval_status', fn (TextColumn $column): bool => $column->isBadge())
            ->assertSee('Pending')
            ->assertSee('Approved')
            ->filterTable('approval_status', 'pending')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$approved])
            ->filterTable('approval_status', 'approved')
            ->assertCanSeeTableRecords([$approved])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_staff_can_review_and_approve_without_exposing_sensitive_data(): void
    {
        Mail::fake();
        $record = Enrollment::factory()->create([
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
        $page = Livewire::actingAs(User::factory()->staff()->create())
            ->test(ViewEnrollment::class, ['record' => $record->getRouteKey()])
            ->assertSee('Pending');
        foreach ([false, true] as $approve) {
            if ($approve) {
                $page->callAction('approveEnrollment')->assertSee('Approved');
            }
            foreach (['private-member@example.com', '9999988888', 'Private home address', '7777766666', 'Private bank transfer', '12,345.00', '6,789.00'] as $privateValue) {
                $page->assertDontSee($privateValue);
            }
            $page->assertActionHidden('sendConfirmationAgain')->assertActionHidden('downloadConfirmation');
        }
    }

    public function test_a_stale_approval_action_reports_no_op_without_sending_another_email(): void
    {
        Mail::fake();
        $actor = User::factory()->staff()->create();
        $this->actingAs($actor);
        $record = Enrollment::factory()->create(['approval_status' => 'pending']);
        $stale = $record->fresh();
        $record->approve($actor);
        $action = ApproveEnrollmentAction::make()->record($stale);
        $action->call();
        $this->assertSame(ActionStatus::Failure, $action->getStatus());
        $this->assertSame('This enrollment is already approved. No confirmation email was queued.', $action->getFailureNotificationTitle());
        Mail::assertQueued(EnrollmentConfirmation::class, 1);
    }
}
