<?php

namespace Tests\Feature;

use App\Documents\EnrollmentConfirmationPdf;
use App\Filament\Resources\Enrollments\Pages\ViewEnrollment;
use App\Models\Enrollment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class EnrollmentConfirmationPdfTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

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

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('if-test-pdf-test-member.pdf', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $contents = ob_get_clean();

        $this->assertStringStartsWith('%PDF-', $contents);
    }

    public function test_admin_can_download_confirmation_from_the_enrollment_page(): void
    {
        $admin = User::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'reference_code' => 'IF-LIVEWIRE-PDF',
            'full_name' => 'Download Test Member',
        ]);

        Livewire::actingAs($admin)
            ->test(ViewEnrollment::class, ['record' => $enrollment->getRouteKey()])
            ->callAction('downloadConfirmation')
            ->assertFileDownloaded('if-livewire-pdf-download-test-member.pdf');
    }

    public function test_pdf_html_lists_only_terms_accepted_in_the_persisted_enrollment(): void
    {
        $acceptedEnrollment = Enrollment::factory()->create([
            'terms_accepted' => true,
        ])->fresh();
        $unacceptedEnrollment = Enrollment::factory()->create([
            'terms_accepted' => false,
        ])->fresh();

        $acceptedHtml = view('pdf.enrollment-confirmation', [
            'enrollment' => $acceptedEnrollment,
        ])->render();
        $unacceptedHtml = view('pdf.enrollment-confirmation', [
            'enrollment' => $unacceptedEnrollment,
        ])->render();

        $this->assertStringContainsString(
            'I accept the gym rules, membership terms, freezing policy, and billing policy.',
            $acceptedHtml,
        );
        $this->assertStringNotContainsString('Terms accepted', $unacceptedHtml);
        $this->assertStringNotContainsString(
            'I accept the gym rules, membership terms, freezing policy, and billing policy.',
            $unacceptedHtml,
        );
    }
}
