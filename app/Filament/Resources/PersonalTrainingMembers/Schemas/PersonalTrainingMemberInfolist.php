<?php

namespace App\Filament\Resources\PersonalTrainingMembers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PersonalTrainingMemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('member_name'),
            TextEntry::make('phone')->placeholder('—'),
            TextEntry::make('trainer.name')->label('Trainer'),
            TextEntry::make('start_date')->date('d M Y'),
            TextEntry::make('end_date')->date('d M Y'),
            TextEntry::make('monthly_fee')->money('INR'),
            IconEntry::make('member_payment_paid')->boolean(),
            IconEntry::make('trainer_payment_paid')->boolean(),
            TextEntry::make('status')->badge(),
        ]);
    }
}
