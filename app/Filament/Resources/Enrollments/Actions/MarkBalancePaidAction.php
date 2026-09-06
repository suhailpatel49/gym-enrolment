<?php

namespace App\Filament\Resources\Enrollments\Actions;

use App\Models\Enrollment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class MarkBalancePaidAction
{
    public static function make(): Action
    {
        return Action::make('markBalancePaid')
            ->label('Mark balance as paid')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Enrollment $record): bool => auth()->check() && $record->approval_status === 'approved' && $record->has_balance && (float) $record->remaining_balance > 0)
            ->authorize(fn (Enrollment $record): bool => $record->approval_status === 'approved' && (auth()->user()?->isAdmin() || auth()->user()?->isStaff()))
            ->requiresConfirmation()
            ->modalHeading('Mark full balance as paid?')
            ->modalDescription(fn (Enrollment $record): string => auth()->user()?->isAdmin()
                ? 'This will add the full balance of ₹'.number_format((float) $record->remaining_balance, 2).' to the recorded amount paid. This action cannot be undone.'
                : 'This will clear the member\'s full pending balance. Confirm that the complete payment was received. This action cannot be undone.')
            ->modalSubmitActionLabel('Confirm full payment')
            ->databaseTransaction()
            ->successNotificationTitle('The full balance was marked as paid.')
            ->failureNotificationTitle('This enrollment is not approved or does not have a remaining balance.')
            ->action(function (Enrollment $record, Action $action): void {
                $lockedRecord = Enrollment::query()
                    ->lockForUpdate()
                    ->findOrFail($record->getKey());

                if (! $lockedRecord->settleOutstandingBalance()) {
                    $action->failure();

                    return;
                }

                $record->refresh();
                $action->success();
            });
    }
}
