<?php

namespace Tests\Feature;

use App\Documents\EnrollmentConfirmationPdf;
use App\Mail\EnrollmentConfirmation;
use App\Mail\NewEnrollmentNotification;
use App\Models\Enrollment;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VisualIdentityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_design_tokens_have_deterministic_tailwind_exports_and_document_dtcg_limitation(): void
    {
        $this->assertFileExists(base_path('tailwind.theme.json'));
        $this->assertFileExists(resource_path('design/tailwind.theme.css'));
        $this->assertFileDoesNotExist(base_path('tokens.json'));
        $this->assertFileDoesNotExist(resource_path('design/tokens.json'));

        $tailwindConfig = json_decode(
            file_get_contents(base_path('tailwind.theme.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $tailwindTheme = file_get_contents(resource_path('design/tailwind.theme.css'));
        $designSystem = file_get_contents(base_path('DESIGN.md'));

        $this->assertSame('#f41e1e', $tailwindConfig['theme']['extend']['colors']['primary']);
        $this->assertSame(['Kanit'], $tailwindConfig['theme']['extend']['fontFamily']['display']);
        $this->assertStringContainsString('--color-primary: #f41e1e;', $tailwindTheme);
        $this->assertStringContainsString('--font-display: "Kanit";', $tailwindTheme);
        $this->assertStringContainsString('DTCG 2025.10', $designSystem);
        $this->assertStringContainsString('schema-valid 2025.10 output', $designSystem);
        $this->assertStringContainsString('not committed', $designSystem);
    }

    public function test_deployment_gates_name_the_pending_independent_checks(): void
    {
        $deploymentRequirements = file_get_contents(base_path('.hermes/deployments/t_bac629bb-incline-visual-system.md'));

        $this->assertStringContainsString('- feature_commit: SELF', $deploymentRequirements);
        $this->assertStringContainsString('- reviewer_signoff: pending independent Reviewer t_a590e2e1', $deploymentRequirements);
        $this->assertStringContainsString('- qa_signoff: pending independent QA t_9cc794ea', $deploymentRequirements);
    }

    public function test_application_themes_do_not_import_tooling_exports_or_override_layout_namespaces(): void
    {
        foreach ([
            resource_path('css/app.css'),
            resource_path('css/filament/admin/theme.css'),
        ] as $themePath) {
            $theme = file_get_contents($themePath);

            $this->assertStringNotContainsString('design/tailwind.theme.css', $theme);
            $this->assertDoesNotMatchRegularExpression('/--(?:spacing|container)-/', $theme);
            $this->assertStringContainsString('--color-primary: #f41e1e;', $theme);
            $this->assertStringContainsString('--font-display:', $theme);
            $this->assertStringContainsString('--radius-sm: 4px;', $theme);
        }
    }

    public function test_public_tablet_surfaces_render_the_accessible_visual_identity(): void
    {
        $loginResponse = $this->get(route('tablet.login'));

        $loginResponse
            ->assertOk()
            ->assertSee('data-visual-system="incline"', false)
            ->assertSee('data-surface="tablet-login"', false)
            ->assertSee('Skip to access form')
            ->assertSee('Incline Fitness')
            ->assertDontSee('hover:text-primary', false);

        $this->withSession(['tablet_authenticated' => true])
            ->get(route('enrollment.create'))
            ->assertOk()
            ->assertSee('data-surface="enrollment"', false)
            ->assertSee('Member details')
            ->assertSee('Terms and confirmation');

        config()->set('gym.tablet_pin', '2468');

        $this->withSession(['tablet_authenticated' => true])
            ->from(route('enrollment.create'))
            ->followingRedirects()
            ->post(route('tablet.logout'), ['pin' => '1111'])
            ->assertOk()
            ->assertSee('aria-labelledby="logout-dialog-title"', false)
            ->assertSee('data-dialog-auto-open', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="logout-pin-error"', false);
    }

    public function test_enrollment_validation_and_success_have_accessible_states(): void
    {
        Livewire::test('enrollment-form')
            ->call('submit')
            ->assertSeeHtml('role="alert"')
            ->assertSeeHtml('aria-invalid="true"')
            ->assertSeeHtml('aria-describedby="fullName-error"');

        Livewire::test('enrollment-form')
            ->set('submittedReference', 'IF-VISUAL-TEST')
            ->assertSeeHtml('data-state="success"')
            ->assertSee('IF-VISUAL-TEST');
    }

    public function test_filament_panel_uses_the_local_incline_theme(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame('resources/css/filament/admin/theme.css', $panel->getViteTheme());
        $this->assertSame('Archivo', $panel->getFontFamily());
        $this->assertSame('#F41E1E', $panel->getColors()['primary']);
    }

    public function test_filament_theme_keeps_dark_chrome_separate_from_adaptive_content(): void
    {
        $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertStringContainsString(
            '@apply bg-canvas text-tertiary dark:bg-gray-950 dark:text-white;',
            $theme,
        );
        $this->assertMatchesRegularExpression(
            '/\.fi-sidebar\s*\{\s*@apply bg-secondary text-white;\s*\}/',
            $theme,
        );
        $this->assertStringNotContainsString('.fi-topbar nav', $theme);
        $this->assertDoesNotMatchRegularExpression(
            '/\.fi-topbar[^,{]*\{[^}]*\b(?:bg-secondary|text-white)\b/s',
            $theme,
        );
        $this->assertMatchesRegularExpression(
            '/\.fi-btn\.fi-color-primary\s*\{\s*@apply bg-tertiary text-white [^;]+;\s*\}/',
            $theme,
        );

        foreach ([
            '.fi-header-heading',
            '.fi-breadcrumbs',
            '.fi-section-header-heading',
            '.fi-in-entry-label',
            '.fi-in-text-item',
            '.fi-ta-text-item',
            '.fi-fo-field-label-content',
            '.fi-sc-text',
            '.fi-fo-field-wrp-error-message',
            '.fi-empty-state-heading',
            '.fi-empty-state-description',
        ] as $contentSelector) {
            $this->assertDoesNotMatchRegularExpression(
                '/'.preg_quote($contentSelector, '/').'[^{}]*\{[^{}]*@apply[^;]*\btext-white\b(?![^;]*\bdark:)/s',
                $theme,
                "The {$contentSelector} content selector must not force a light foreground.",
            );
        }
    }

    public function test_html_mail_is_branded_table_safe_and_keeps_dynamic_data(): void
    {
        $enrollment = Enrollment::factory()->create([
            'reference_code' => 'IF-MAIL-VISUAL',
            'full_name' => 'Asha Patel',
            'membership_package' => '6 Months',
            'amount_paid' => '7250.00',
        ]);

        foreach ([
            new EnrollmentConfirmation($enrollment),
            new NewEnrollmentNotification($enrollment),
        ] as $mailable) {
            $html = $mailable->render();

            $this->assertStringContainsString('data-visual-system="incline"', $html);
            $this->assertStringContainsString('role="presentation"', $html);
            $this->assertStringContainsString('#F41E1E', $html);
            $this->assertStringContainsString('IF-MAIL-VISUAL', $html);
            $this->assertDoesNotMatchRegularExpression('/(?:src|href)=["\']https?:/i', $html);
        }
    }

    public function test_pdf_is_branded_dompdf_safe_and_keeps_dynamic_data(): void
    {
        $enrollment = Enrollment::factory()->create([
            'reference_code' => 'IF-PDF-VISUAL',
            'full_name' => 'Ravi Shah',
            'membership_package' => '12 Months',
            'payment_mode' => 'card',
            'amount_paid' => '9000.00',
        ]);
        $html = view('pdf.enrollment-confirmation', ['enrollment' => $enrollment])->render();
        $pdf = app(EnrollmentConfirmationPdf::class)->render($enrollment);

        $this->assertStringContainsString('data-visual-system="incline"', $html);
        $this->assertStringContainsString('#F41E1E', $html);
        $this->assertStringContainsString('IF-PDF-VISUAL', $html);
        $this->assertStringContainsString('12 Months', $html);
        $this->assertStringContainsString('INR 9,000.00', $html);
        $this->assertDoesNotMatchRegularExpression('/(?:src|href|url\()\s*["\']?https?:/i', $html);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
