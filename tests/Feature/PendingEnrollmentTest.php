<?php

namespace Tests\Feature;

use App\Documents\EnrollmentConfirmationPdf;
use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Resources\Enrollments\Pages\ViewEnrollment;
use App\Filament\Widgets\EnrollmentStats;
use App\Filament\Widgets\OverdueBalances;
use App\Filament\Widgets\RenewalsDue;
use App\Models\Enrollment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PendingEnrollmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    private function pending(): Enrollment
    {
        return Enrollment::factory()->create([
            'approval_status' => 'pending',
            'membership_start_date' => today()->subDay(),
            'membership_end_date' => today()->addDays(3),
            'has_balance' => true,
            'amount_paid' => 12345,
            'remaining_balance' => 6789,
            'balance_due_date' => today()->subDay(),
        ]);
    }

    public function test_pending_balance_cannot_be_settled(): void
    {
        $record = $this->pending();
        $this->assertFalse($record->settleOutstandingBalance());
        $this->assertSame('12345.00', $record->refresh()->amount_paid);
        $this->assertTrue($record->has_balance);
        $this->assertSame('6789.00', $record->remaining_balance);
    }

    public function test_pending_confirmation_pdf_is_forbidden(): void
    {
        try {
            app(EnrollmentConfirmationPdf::class)->download($this->pending());
            $this->fail('Pending enrollment must not have a membership confirmation.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    /** @return array<int, array{string}> */
    public static function operationalActions(): array
    {
        return [['markBalancePaid'], ['downloadConfirmation'], ['sendConfirmationAgain']];
    }

    #[DataProvider('operationalActions')]
    public function test_pending_membership_actions_are_hidden_and_cannot_be_called(string $action): void
    {
        Mail::fake();
        $record = $this->pending();
        Livewire::test(ViewEnrollment::class, ['record' => $record->getRouteKey()])
            ->assertActionHidden($action)
            ->call('mountAction', $action)
            ->call('callMountedAction');
        Livewire::test(ListEnrollments::class)
            ->assertTableActionHidden($action, $record)
            ->call('mountAction', $action, [], ['table' => true, 'recordKey' => (string) $record->getKey()])
            ->call('callMountedAction');
        $this->assertTrue($record->refresh()->has_balance);
        Mail::assertNothingQueued();
    }

    /** @return array<int, array{class-string}> */
    public static function membershipWidgets(): array
    {
        return [[RenewalsDue::class], [OverdueBalances::class]];
    }

    #[DataProvider('membershipWidgets')]
    public function test_pending_records_are_excluded_from_operational_widgets(string $widget): void
    {
        $pending = $this->pending();
        $approved = $this->pending();
        $approved->update(['approval_status' => 'approved']);
        Livewire::test($widget)->assertCanSeeTableRecords([$approved])->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_pending_records_do_not_contribute_to_approved_membership_statistics(): void
    {
        $before = Livewire::test(EnrollmentStats::class)->html();
        $this->pending();
        $after = Livewire::test(EnrollmentStats::class)->html();
        $values = static function (string $html): array {
            preg_match_all('/class="fi-wi-stats-overview-stat-value">\s*(.*?)\s*<\/div>/s', $html, $matches);

            return array_slice($matches[1], 1);
        };
        $this->assertCount(5, $values($before));
        $this->assertSame($values($before), $values($after));
    }

    public function test_active_and_balance_filters_do_not_include_pending_memberships(): void
    {
        $pending = $this->pending();
        Livewire::test(ListEnrollments::class)->filterTable('membership_status', 'active')
            ->assertCanNotSeeTableRecords([$pending]);
        Livewire::test(ListEnrollments::class)->filterTable('balance_status', 'overdue')
            ->assertCanNotSeeTableRecords([$pending]);
    }
}
