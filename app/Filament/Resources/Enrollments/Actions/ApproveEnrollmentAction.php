<?php

namespace App\Filament\Resources\Enrollments\Actions;

use App\Models\Enrollment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class ApproveEnrollmentAction
{
    public static function make(): Action
    {
        return Action::make('approveEnrollment')
            ->label('Approve enrollment')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Enrollment $record): bool => $record->approval_status === 'pending')
            ->authorize('approve')
            ->requiresConfirmation()
            ->modalHeading('Approve enrollment?')
            ->modalDescription('Approval activates membership and emails confirmation to the member.')
            ->modalSubmitActionLabel('Approve enrollment')
            ->successNotificationTitle('Enrollment approved. The member confirmation email was queued.')
            ->failureNotificationTitle('This enrollment is already approved. No confirmation email was queued.')
            ->action(function (Enrollment $record, Action $action): void {
                if (! $record->approve(auth()->user())) {
                    $action->failure();

                    return;
                }

                $action->success();
            });
    }
}
