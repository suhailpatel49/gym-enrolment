<?php

namespace Tests\Feature;

use App\Mail\EnrollmentConfirmation;
use App\Mail\NewEnrollmentNotification;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EnrollmentMailContentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_member_and_gym_emails_use_the_branded_confirmation_content(): void
    {
        $enrollment = Enrollment::factory()->create([
            'reference_code' => 'IF-MAIL-TEST',
            'full_name' => 'Test Member',
            'freezing_enabled' => true,
            'freezing_days' => 10,
            'has_balance' => true,
            'remaining_balance' => '2500.00',
            'balance_due_date' => today()->addDays(7),
        ]);

        (new EnrollmentConfirmation($enrollment))
            ->assertSeeInHtml('Incline Fitness')
            ->assertSeeInHtml('Membership confirmation')
            ->assertSeeInHtml('IF-MAIL-TEST')
            ->assertSeeInHtml('Remaining balance')
            ->assertSeeInText('IF-MAIL-TEST')
            ->assertSeeInText('INR 2,500.00');

        (new NewEnrollmentNotification($enrollment))
            ->assertSeeInHtml('New membership enrollment')
            ->assertSeeInHtml('Test Member')
            ->assertSeeInHtml('IF-MAIL-TEST')
            ->assertSeeInText('Test Member')
            ->assertSeeInText('INR 2,500.00');
    }

    public function test_member_email_lists_only_terms_accepted_in_the_persisted_enrollment(): void
    {
        $acceptedEnrollment = Enrollment::factory()->create([
            'terms_accepted' => true,
        ])->fresh();
        $unacceptedEnrollment = Enrollment::factory()->create([
            'terms_accepted' => false,
        ])->fresh();

        (new EnrollmentConfirmation($acceptedEnrollment))
            ->assertSeeInHtml('I accept the gym rules, membership terms, freezing policy, and billing policy.')
            ->assertSeeInText('I accept the gym rules, membership terms, freezing policy, and billing policy.');

        (new EnrollmentConfirmation($unacceptedEnrollment))
            ->assertDontSeeInHtml('Terms accepted')
            ->assertDontSeeInText('Terms accepted:')
            ->assertDontSeeInHtml('I accept the gym rules, membership terms, freezing policy, and billing policy.')
            ->assertDontSeeInText('I accept the gym rules, membership terms, freezing policy, and billing policy.');
    }
}
