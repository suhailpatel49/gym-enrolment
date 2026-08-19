<?php

namespace Tests\Feature;

use App\Filament\Resources\Enrollments\Pages\ViewEnrollment;
use App\Mail\EnrollmentConfirmation;
use App\Mail\NewEnrollmentNotification;
use App\Models\Enrollment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class EnrollmentEmailActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_confirmation_can_be_sent_again_only_to_the_member(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'email' => 'member@example.com',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($user)
            ->test(ViewEnrollment::class, ['record' => $enrollment->getRouteKey()])
            ->callAction('sendConfirmationAgain');

        Mail::assertQueued(
            EnrollmentConfirmation::class,
            fn (EnrollmentConfirmation $mail): bool => $mail->hasTo('member@example.com'),
        );
        Mail::assertNotQueued(NewEnrollmentNotification::class);
        Mail::assertQueuedCount(1);
    }
}
