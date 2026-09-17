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

    private const MEMBERSHIP_TERMS = [
        'Membership fees are non-refundable under any circumstances.',
        'Membership does not cover medical conditions or accidents.',
        'Additional charges apply for membership freezing and transfer, as per terms and conditions.',
        'Any outstanding membership balance must be cleared or upgraded within 15 days.',
        'Failure to clear dues within 15 days will result in automatic membership downgrade.',
        'Management is not liable for any injury, illness, or loss of life.',
        'Membership can only be transferred to a new member.',
        'The gym is not responsible for loss of belongings or damage.',
    ];

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

        $this->assertStringContainsString('Terms accepted', $acceptedHtml);
        $this->assertStringNotContainsString(Enrollment::TERMS_ACCEPTANCE_TEXT, $acceptedHtml);
        $this->assertStringContainsString('Keep this confirmation for your records.', $acceptedHtml);
        $previousPosition = -1;

        foreach (self::MEMBERSHIP_TERMS as $term) {
            $position = strpos($acceptedHtml, $term);

            $this->assertNotFalse($position, "Failed asserting that the PDF contains: {$term}");
            $this->assertGreaterThan($previousPosition, $position, "Failed asserting that the PDF term appears in order: {$term}");

            $previousPosition = $position;
        }

        $this->assertStringNotContainsString('Terms accepted', $unacceptedHtml);

        foreach (self::MEMBERSHIP_TERMS as $term) {
            $this->assertStringNotContainsString($term, $unacceptedHtml);
        }
    }
}
