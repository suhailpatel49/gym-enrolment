<?php

namespace App\Filament\Resources\PersonalTrainingMembers\Pages;

use App\Filament\Resources\PersonalTrainingMembers\PersonalTrainingMemberResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePersonalTrainingMember extends CreateRecord
{
    protected static string $resource = PersonalTrainingMemberResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return null;
    }
}
