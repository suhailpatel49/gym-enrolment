<?php

namespace Tests\Feature;

use App\Documents\EnrollmentConfirmationPdf;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EnrollmentConfirmationPdfTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_membership_confirmation_can_be_downloaded_as_a_pdf(): void
    {
        $enrollment = Enrollment::factory()->create([
            'reference_code' => 'IF-TEST-PDF',
            'full_name' => 'Test Member',
            'has_balance' => true,
            'remaining_balance' => '2500.00',
            'balance_due_date' => today()->addDays(7),
        ]);

        $response = app(EnrollmentConfirmationPdf::class)->download($enrollment);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('if-test-pdf-test-member.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
