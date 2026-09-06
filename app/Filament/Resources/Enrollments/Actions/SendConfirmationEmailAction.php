<?php

namespace App\Filament\Resources\Enrollments\Actions;

use App\Mail\EnrollmentConfirmation;
use App\Models\Enrollment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;

class SendConfirmationEmailAction
{
    public static function make(): Action
    {
        return Action::make('sendConfirmationAgain')
            ->label('Send confirmation again')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (Enrollment $record): bool => $record->approval_status === 'approved' && (auth()->user()?->isAdmin() ?? false) && filled($record->email))
            ->authorize(fn (Enrollment $record): bool => $record->approval_status === 'approved' && (auth()->user()?->isAdmin() ?? false))
            ->requiresConfirmation()
            ->modalHeading('Send confirmation email again?')
            ->modalDescription(fn (Enrollment $record): string => 'A new membership confirmation email will be sent only to '.$record->email.'. The gym will not receive another email.')
            ->modalSubmitActionLabel('Send email')
            ->successNotificationTitle('The member confirmation email was queued.')
            ->action(function (Enrollment $record, Action $action): void {
                abort_unless($record->refresh()->approval_status === 'approved', 403);

                Mail::to($record->email)->queue(new EnrollmentConfirmation($record));

                $action->success();
            });
    }
}
