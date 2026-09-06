<?php

namespace App\Filament\Resources\Trainers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TrainerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('phone')->tel()->maxLength(255),
            Toggle::make('active')->default(true),
        ]);
    }
}
