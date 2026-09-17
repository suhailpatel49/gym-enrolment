<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class EnrollmentFormTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_form_lists_all_membership_terms_in_order_and_keeps_the_acceptance_sentence(): void
    {
        Livewire::test('enrollment-form')
            ->assertSeeInOrder([
                'Membership fees are non-refundable under any circumstances.',
                'Membership does not cover medical conditions or accidents.',
                'Additional charges apply for membership freezing and transfer, as per terms and conditions.',
                'Any outstanding membership balance must be cleared or upgraded within 15 days.',
                'Failure to clear dues within 15 days will result in automatic membership downgrade.',
                'Management is not liable for any injury, illness, or loss of life.',
                'Membership can only be transferred to a new member.',
                'The gym is not responsible for loss of belongings or damage.',
                'I accept the gym rules, membership terms, freezing policy, and billing policy.',
            ]);
    }

    public function test_required_fields_are_validated(): void
    {
        Livewire::test('enrollment-form')
            ->call('review')
            ->assertHasErrors([
                'email',
                'fullName',
                'mobileNumber',
                'dateOfBirth',
                'packageMonths',
                'paymentMode',
                'amountPaid',
                'membershipEndDate',
                'termsAccepted',
            ]);
    }

    public function test_start_date_defaults_to_today_and_end_date_is_calculated(): void
    {
        $this->travelTo(Carbon::parse('2026-08-19 10:00:00'));

        Livewire::test('enrollment-form')
            ->assertSet('membershipStartDate', '2026-08-19')
            ->set('packageMonths', '3')
            ->assertSet('membershipEndDate', '2026-11-18')
            ->set('packageMonths', 'other')
            ->assertSet('membershipEndDate', '')
            ->set('customPackageMonths', '5')
            ->assertSet('membershipEndDate', '2027-01-18');
    }

    public function test_numeric_fields_cannot_use_values_below_their_minimum(): void
    {
        Livewire::test('enrollment-form')
            ->set('packageMonths', 'other')
            ->set('customPackageMonths', '-2')
            ->assertSet('customPackageMonths', '1')
            ->set('freezingEnabled', true)
            ->set('freezingDays', '0')
            ->assertSet('freezingDays', '1')
            ->set('amountPaid', '-100')
            ->assertSet('amountPaid', '0')
            ->set('hasBalance', true)
            ->set('remainingBalance', '-50')
            ->assertSet('remainingBalance', '0.01');
    }

    public function test_custom_package_months_and_payment_values_are_preserved(): void
    {
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.connection', null);
        Mail::fake();

        Livewire::test('enrollment-form')
            ->set('email', 'member@example.com')
            ->set('fullName', 'Ravi Shah')
            ->set('mobileNumber', '9876543210')
            ->set('dateOfBirth', '1990-02-01')
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
            ->set('termsAccepted', true)
            ->call('review')
            ->call('approve')
            ->assertHasNoErrors();

        $enrollment = Enrollment::query()->firstOrFail();

        $this->assertSame(2, $enrollment->package_months);
        $this->assertSame('2 Months', $enrollment->membership_package);
        $this->assertSame('2026-10-31', $enrollment->membership_end_date->toDateString());
        $this->assertSame('Bank transfer', $enrollment->payment_mode);
        $this->assertTrue($enrollment->freezing_enabled);
        $this->assertSame(10, $enrollment->freezing_days);
        $this->assertTrue($enrollment->has_balance);
        $this->assertSame('500.00', $enrollment->remaining_balance);
        $this->assertSame('2026-09-10', $enrollment->balance_due_date->toDateString());
    }
}
