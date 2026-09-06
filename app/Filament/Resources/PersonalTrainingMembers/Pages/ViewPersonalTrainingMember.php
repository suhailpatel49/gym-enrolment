<?php

namespace App\Filament\Resources\PersonalTrainingMembers\Pages;

use App\Filament\Resources\PersonalTrainingMembers\PersonalTrainingMemberResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPersonalTrainingMember extends ViewRecord
{
    protected static string $resource = PersonalTrainingMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
