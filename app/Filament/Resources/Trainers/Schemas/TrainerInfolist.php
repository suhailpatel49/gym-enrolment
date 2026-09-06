<?php

namespace App\Filament\Resources\Trainers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TrainerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('phone')->placeholder('—'),
            IconEntry::make('active')->boolean(),
        ]);
    }
}
