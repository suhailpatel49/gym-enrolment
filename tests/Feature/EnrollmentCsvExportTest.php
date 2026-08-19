<?php

namespace Tests\Feature;

use App\Exports\EnrollmentCsvExport;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EnrollmentCsvExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_enrollments_can_be_downloaded_as_a_safe_csv(): void
    {
        Enrollment::factory()->create([
            'full_name' => '=Unsafe formula',
            'mobile_number' => '9876543210',
        ]);

        $response = app(EnrollmentCsvExport::class)->download(Enrollment::query());

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        $this->assertStringContainsString('Member Full Name', $content);
        $this->assertStringContainsString("'=Unsafe formula", $content);
        $this->assertStringContainsString('9876543210', $content);
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
    }
}
