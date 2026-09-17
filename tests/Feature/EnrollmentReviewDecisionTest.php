<?php

namespace Tests\Feature;

use App\Mail\EnrollmentConfirmation;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class EnrollmentReviewDecisionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_continue_shows_every_entered_value_and_three_decision_actions_without_persisting_or_mailing(): void
    {
        Mail::fake();

        $component = $this->completedForm()
            ->call('review')
            ->assertHasNoErrors()
            ->assertSet('reviewing', true)
            ->assertSee('Review your enrollment')
            ->assertSeeInOrder(['Go Back', 'Approve', 'Reject']);

        foreach ([
            'Asha Patel', 'member@example.com', '12 Hill Road', '9876543210', '9988776655',
            '1995-05-10', '2 Months', '10 days', 'Bank transfer', '4500', '2026-09-01',
            '2026-10-31', '500', '2026-09-10', Enrollment::TERMS_ACCEPTANCE_TEXT,
        ] as $value) {
            $component->assertSee($value);
        }

        $this->assertDatabaseCount('enrollments', 0);
        Mail::assertNothingQueued();
    }

    public function test_go_back_preserves_all_entered_values_and_sends_no_email(): void
    {
        Mail::fake();

        $this->completedForm()
            ->call('review')
            ->call('goBack')
            ->assertSet('reviewing', false)
            ->assertSet('fullName', 'Asha Patel')
            ->assertSet('address', '12 Hill Road')
            ->assertSet('customPackageMonths', '2')
            ->assertSet('freezingDays', '10')
            ->assertSet('otherPaymentMode', 'Bank transfer')
            ->assertSet('remainingBalance', '500')
            ->assertSet('termsAccepted', true);

        $this->assertDatabaseCount('enrollments', 0);
        Mail::assertNothingQueued();
    }

    public function test_approve_persists_an_approved_enrollment_and_queues_one_member_confirmation(): void
    {
        Mail::fake();

        $component = $this->completedForm()->call('review');
        $repeatedRequest = $this->completedForm()
            ->call('review')
            ->set('reviewReference', $component->get('reviewReference'));

        Mail::assertNothingQueued();

        $component
            ->call('approve')
            ->assertHasNoErrors()
            ->assertSet('submittedDecision', 'approved')
            ->assertSee('Enrollment approved');

        $enrollment = Enrollment::query()->sole();

        $this->assertSame('approved', $enrollment->approval_status);
        $this->assertNull($enrollment->approved_by);
        $this->assertNotNull($enrollment->approved_at);
        Mail::assertQueued(
            EnrollmentConfirmation::class,
            fn (EnrollmentConfirmation $mail): bool => $mail->hasTo('member@example.com'),
        );
        Mail::assertQueuedCount(1);

        $component->call('approve');
        $repeatedRequest
            ->call('approve')
            ->assertSet('submittedDecision', 'approved');

        $this->assertDatabaseCount('enrollments', 1);
        Mail::assertQueuedCount(1);
    }

    public function test_reject_persists_a_rejected_enrollment_without_sending_email(): void
    {
        Mail::fake();

        $component = $this->completedForm()
            ->call('review')
            ->call('reject')
            ->assertHasNoErrors()
            ->assertSet('submittedDecision', 'rejected')
            ->assertSee('Enrollment rejected');

        $enrollment = Enrollment::query()->sole();

        $this->assertSame('rejected', $enrollment->approval_status);
        $this->assertNull($enrollment->approved_by);
        $this->assertNull($enrollment->approved_at);
        Mail::assertNothingQueued();

        $component->call('reject');

        $this->assertDatabaseCount('enrollments', 1);
        Mail::assertNothingQueued();
    }

    private function completedForm(): Testable
    {
        return Livewire::test('enrollment-form')
            ->set('email', 'member@example.com')
            ->set('fullName', 'Asha Patel')
            ->set('address', '12 Hill Road')
            ->set('mobileNumber', '9876543210')
            ->set('emergencyContact', '9988776655')
            ->set('dateOfBirth', '1995-05-10')
            ->set('packageMonths', 'other')
            ->set('customPackageMonths', '2')
            ->set('freezingEnabled', true)
            ->set('freezingDays', '10')
            ->set('paymentMode', 'other')
            ->set('otherPaymentMode', 'Bank transfer')
            ->set('amountPaid', '4500')
            ->set('membershipStartDate', '2026-09-01')
            ->set('hasBalance', true)
            ->set('remainingBalance', '500')
            ->set('balanceDueDate', '2026-09-10')
            ->set('termsAccepted', true);
    }
}
