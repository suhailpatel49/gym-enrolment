<?php

namespace App\Filament\Resources\PersonalTrainingMembers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PersonalTrainingMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('member_name')->required()->maxLength(255),
            TextInput::make('phone')->tel()->maxLength(255),
            Select::make('trainer_id')->relationship('trainer', 'name')->searchable()->preload()->required()->exists('trainers', 'id'),
            DatePicker::make('start_date')->required(),
            DatePicker::make('end_date')->required()->afterOrEqual('start_date'),
            TextInput::make('monthly_fee')->numeric()->required()->minValue(0)->maxValue(99999999.99)->step('0.01')->rules(['decimal:0,2'])->prefix('₹'),
            Toggle::make('member_payment_paid')->label('Member payment paid')->default(false),
            Toggle::make('trainer_payment_paid')->label('Trainer payment paid')->default(false),
            Toggle::make('active')->default(true),
        ]);
    }
}
