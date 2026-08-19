<?php

namespace Tests\Feature;

use Tests\TestCase;

class TabletAccessTest extends TestCase
{
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

        $this->withSession(['tablet_authenticated' => true])
            ->post(route('tablet.logout'), ['pin' => '2468'])
            ->assertRedirect(route('tablet.login'))
            ->assertSessionMissing('tablet_authenticated');
    }
}
