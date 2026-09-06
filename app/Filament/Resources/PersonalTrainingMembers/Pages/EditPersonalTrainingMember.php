<?php

namespace App\Filament\Resources\PersonalTrainingMembers\Pages;

use App\Filament\Resources\PersonalTrainingMembers\PersonalTrainingMemberResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPersonalTrainingMember extends EditRecord
{
    protected static string $resource = PersonalTrainingMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return null;
    }
}
