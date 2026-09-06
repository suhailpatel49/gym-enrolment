<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Filament\Resources\Enrollments\Actions\ApproveEnrollmentAction;
use App\Filament\Resources\Enrollments\Actions\DownloadConfirmationAction;
use App\Filament\Resources\Enrollments\Actions\MarkBalancePaidAction;
use App\Filament\Resources\Enrollments\Actions\SendConfirmationEmailAction;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEnrollment extends ViewRecord
{
    protected static string $resource = EnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ApproveEnrollmentAction::make(),
            DownloadConfirmationAction::make(),
            SendConfirmationEmailAction::make(),
            MarkBalancePaidAction::make(),
        ];
    }
}
