<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EnrollmentApprovalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_generic_records_default_to_approved_without_fabricated_audit_data(): void
    {
        $this->assertSame('approved', (new Enrollment)->approval_status);

        $record = Enrollment::factory()->create()->refresh();

        $this->assertSame('approved', $record->approval_status);
        $this->assertNull($record->approved_by);
        $this->assertNull($record->approved_at);
    }

    public function test_rejected_records_are_final_and_have_no_approval_audit(): void
    {
        $record = Enrollment::factory()->create([
            'approval_status' => 'rejected',
            'approved_by' => null,
            'approved_at' => null,
        ])->refresh();

        $this->assertSame('rejected', $record->approval_status);
        $this->assertNull($record->approved_by);
        $this->assertNull($record->approved_at);
    }
}
