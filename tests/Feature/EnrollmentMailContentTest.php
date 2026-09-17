<?php

namespace Tests\Feature;

use App\Mail\EnrollmentConfirmation;
use App\Mail\NewEnrollmentNotification;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Tests\TestCase;

class EnrollmentMailContentTest extends TestCase
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
            ->assertSeeInText('INR 2,500.00')
            ->assertDontSeeInHtml('This is an automated email from Laravel.')
            ->assertDontSeeInHtml('Please keep this message for your records.')
            ->assertDontSeeInText('Please keep this message for your records.');

        (new NewEnrollmentNotification($enrollment))
            ->assertSeeInHtml('New membership enrollment')
            ->assertSeeInHtml('Test Member')
            ->assertSeeInHtml('IF-MAIL-TEST')
            ->assertSeeInText('Test Member')
            ->assertSeeInText('INR 2,500.00')
            ->assertSeeInHtml('This is an automated email from Laravel.')
            ->assertSeeInHtml('Please keep this message for your records.');
    }

    public function test_member_email_lists_only_terms_accepted_in_the_persisted_enrollment(): void
    {
        $acceptedEnrollment = Enrollment::factory()->create([
            'terms_accepted' => true,
        ])->fresh();
        $unacceptedEnrollment = Enrollment::factory()->create([
            'terms_accepted' => false,
        ])->fresh();

        $acceptedMail = new EnrollmentConfirmation($acceptedEnrollment);
        $acceptedHtml = $acceptedMail->render();
        $acceptedText = view('mail.enrollment-confirmation-text', [
            'enrollment' => $acceptedEnrollment,
        ])->render();

        $this->assertStringContainsString('Terms accepted', $acceptedHtml);
        $this->assertStringContainsString('Terms accepted:', $acceptedText);
        $this->assertStringNotContainsString(Enrollment::TERMS_ACCEPTANCE_TEXT, $acceptedHtml);
        $this->assertStringNotContainsString(Enrollment::TERMS_ACCEPTANCE_TEXT, $acceptedText);
        $this->assertTermsAppearInOrder($acceptedHtml);
        $this->assertTermsAppearInOrder($acceptedText);

        $unacceptedMail = new EnrollmentConfirmation($unacceptedEnrollment);
        $unacceptedHtml = $unacceptedMail->render();
        $unacceptedText = view('mail.enrollment-confirmation-text', [
            'enrollment' => $unacceptedEnrollment,
        ])->render();

        $this->assertStringNotContainsString('Terms accepted', $unacceptedHtml);
        $this->assertStringNotContainsString('Terms accepted:', $unacceptedText);

        foreach (self::MEMBERSHIP_TERMS as $term) {
            $this->assertStringNotContainsString($term, $unacceptedHtml);
            $this->assertStringNotContainsString($term, $unacceptedText);
        }
    }

    public function test_member_email_embeds_the_tracked_logo_inline(): void
    {
        $enrollment = Enrollment::factory()->create([
            'reference_code' => 'IF-LOGO-TEST',
            'full_name' => 'Logo Test Member',
            'terms_accepted' => true,
        ]);
        $symfonyMessage = null;
        $mailable = (new EnrollmentConfirmation($enrollment))
            ->to('member@example.test')
            ->withSymfonyMessage(function (Email $message) use (&$symfonyMessage): void {
                $symfonyMessage = $message;
            });

        app('mailer')->sendNow($mailable);

        $this->assertInstanceOf(Email::class, $symfonyMessage);

        $document = new \DOMDocument;
        @$document->loadHTML($symfonyMessage->getHtmlBody());
        $logo = (new \DOMXPath($document))->query('//img[@alt="Incline Fitness"]')->item(0);

        $this->assertInstanceOf(\DOMElement::class, $logo);
        $this->assertStringStartsWith('cid:', $logo->getAttribute('src'));
        $this->assertSame('240', $logo->getAttribute('width'));
        $this->assertSame('60', $logo->getAttribute('height'));
        $this->assertStringContainsString('max-width:100%', $logo->getAttribute('style'));

        $inlineLogos = array_values(array_filter(
            $symfonyMessage->getAttachments(),
            fn (DataPart $part): bool => $part->getDisposition() === 'inline'
                && $part->getFilename() === 'incline-fitness-logo.png',
        ));

        $this->assertCount(1, $inlineLogos);
        $this->assertSame(
            '7c01ad394253b9b68672ab29a3311b7e070efc37f25d3233700c7b232b2a6720',
            hash('sha256', $inlineLogos[0]->getBody()),
        );

        foreach (self::MEMBERSHIP_TERMS as $term) {
            $this->assertStringContainsString($term, $symfonyMessage->getHtmlBody());
        }

        $adminHtml = (new NewEnrollmentNotification($enrollment))->render();

        $this->assertStringNotContainsString('alt="Incline Fitness"', $adminHtml);
    }

    private function assertTermsAppearInOrder(string $content): void
    {
        $previousPosition = -1;

        foreach (self::MEMBERSHIP_TERMS as $term) {
            $position = strpos($content, $term);

            $this->assertNotFalse($position, "Failed asserting that the output contains: {$term}");
            $this->assertGreaterThan($previousPosition, $position, "Failed asserting that the term appears in order: {$term}");

            $previousPosition = $position;
        }
    }
}
