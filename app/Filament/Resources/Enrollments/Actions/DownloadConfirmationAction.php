<?php

namespace App\Filament\Resources\Enrollments\Actions;

use App\Documents\EnrollmentConfirmationPdf;
use App\Models\Enrollment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Response;

class DownloadConfirmationAction
{
    public static function make(): Action
    {
        return Action::make('downloadConfirmation')
            ->label('Download confirmation')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
            ->authorize(fn (): bool => auth()->user()?->isAdmin() ?? false)
            ->action(fn (Enrollment $record): Response => app(EnrollmentConfirmationPdf::class)->download($record));
    }
}
