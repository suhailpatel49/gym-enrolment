<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EnrollmentBalanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_full_outstanding_balance_can_be_settled(): void
    {
        $enrollment = Enrollment::factory()->create([
            'amount_paid' => '10000.00',
            'has_balance' => true,
            'remaining_balance' => '2500.00',
            'balance_due_date' => today()->addDays(5),
        ]);

        $this->assertTrue($enrollment->settleOutstandingBalance());

        $enrollment->refresh();

        $this->assertSame('12500.00', $enrollment->amount_paid);
        $this->assertFalse($enrollment->has_balance);
        $this->assertNull($enrollment->remaining_balance);
        $this->assertNull($enrollment->balance_due_date);
        $this->assertFalse($enrollment->settleOutstandingBalance());
    }
}
