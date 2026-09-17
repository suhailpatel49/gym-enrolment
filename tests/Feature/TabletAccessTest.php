<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class TabletAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_enrollment_form_requires_tablet_access(): void
    {
        $this->get(route('enrollment.create'))
            ->assertRedirect(route('tablet.login'));
    }

    public function test_incorrect_pin_is_rejected(): void
    {
        config()->set('gym.tablet_pin', '2468');

        $this->post(route('tablet.authenticate'), ['pin' => '1111'])
            ->assertSessionHasErrors('pin');
    }

    public function test_correct_pin_opens_enrollment_form(): void
    {
        config()->set('gym.tablet_pin', '2468');

        $this->post(route('tablet.authenticate'), ['pin' => '2468'])
            ->assertRedirect(route('enrollment.create'))
            ->assertSessionHas('tablet_authenticated', true);

        $this->withSession(['tablet_authenticated' => true])
            ->get(route('enrollment.create'))
            ->assertOk()
            ->assertSeeLivewire('enrollment-form');
    }

    public function test_incorrect_pin_does_not_log_out_tablet(): void
    {
        config()->set('gym.tablet_pin', '2468');

        $this->withSession(['tablet_authenticated' => true])
            ->from(route('enrollment.create'))
            ->post(route('tablet.logout'), ['pin' => '1111'])
            ->assertRedirect(route('enrollment.create'))
            ->assertSessionHasErrors('pin', null, 'logout')
            ->assertSessionHas('tablet_authenticated', true);
    }

    public function test_correct_pin_logs_out_tablet(): void
    {
        config()->set('gym.tablet_pin', '2468');

        $this->withSession([
            'tablet_authenticated' => true,
            'enrollment_reviews' => ['stale-token' => ['reference' => 'IF-STALE']],
        ])
            ->post(route('tablet.logout'), ['pin' => '2468'])
            ->assertRedirect(route('tablet.login'))
            ->assertSessionMissing('tablet_authenticated')
            ->assertSessionMissing('enrollment_reviews');
    }

    public function test_revoked_tablet_cannot_approve_through_a_livewire_update_loaded_while_authorized(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('gym.tablet_pin', '2468');

        $page = $this->withSession(['tablet_authenticated' => true])
            ->get(route('enrollment.create'))
            ->assertOk();

        $review = $this->livewireUpdate($this->snapshotFrom($page), [
            'email' => 'member@example.com',
            'fullName' => 'Asha Patel',
            'address' => '12 Hill Road',
            'mobileNumber' => '9876543210',
            'emergencyContact' => '9988776655',
            'dateOfBirth' => '1995-05-10',
            'packageMonths' => '3',
            'freezingEnabled' => false,
            'paymentMode' => 'cash',
            'amountPaid' => '4500',
            'membershipStartDate' => '2026-09-01',
            'hasBalance' => false,
            'termsAccepted' => true,
        ], 'review')->assertOk();

        $reviewSnapshot = json_decode($review->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('enroll', $reviewSnapshot['memo']['path']);
        Livewire::flushState();

        $this->post(route('tablet.logout'), ['pin' => '2468'])
            ->assertRedirect(route('tablet.login'))
            ->assertSessionMissing('tablet_authenticated');
        $this->get(route('enrollment.create'))->assertRedirect(route('tablet.login'));

        $this->livewireUpdate(json_encode($reviewSnapshot, JSON_THROW_ON_ERROR), [], 'approve')
            ->assertRedirect(route('tablet.login'));

        $this->assertDatabaseCount('enrollments', 0);
    }

    private function snapshotFrom(TestResponse $response): string
    {
        preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);

        $this->assertArrayHasKey(1, $matches, 'The enrollment Livewire snapshot was not rendered.');

        return html_entity_decode($matches[1], ENT_QUOTES);
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function livewireUpdate(string $snapshot, array $updates, string $method): TestResponse
    {
        return $this->withHeader('X-Livewire', 'true')->postJson(route('default-livewire.update'), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => $updates,
                'calls' => [[
                    'path' => '',
                    'method' => $method,
                    'params' => [],
                ]],
            ]],
        ]);
    }
}
