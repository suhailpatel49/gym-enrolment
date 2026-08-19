<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Exports\EnrollmentCsvExport;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListEnrollments extends ListRecords
{
    protected static string $resource = EnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadEnrollments')
                ->label('Download CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->authorize(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->action(fn (): StreamedResponse => app(EnrollmentCsvExport::class)->download($this->getTableQueryForExport())),
        ];
    }
}
