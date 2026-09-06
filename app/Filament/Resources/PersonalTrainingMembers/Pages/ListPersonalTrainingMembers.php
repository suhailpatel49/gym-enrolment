<?php

namespace App\Filament\Resources\PersonalTrainingMembers\Pages;

use App\Filament\Resources\PersonalTrainingMembers\PersonalTrainingMemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPersonalTrainingMembers extends ListRecords
{
    protected static string $resource = PersonalTrainingMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
