<?php

namespace App\Filament\Resources\PersonalTrainingMembers\Schemas;

use App\Models\PersonalTrainingMember;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PersonalTrainingMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('client_name')->label('Client name')->required()->maxLength(255),
            TextInput::make('phone')->tel()->required()->maxLength(255),
            Select::make('trainer_id')->relationship('trainer', 'name')->searchable()->preload()->required()->exists('trainers', 'id'),
            TextInput::make('payment_mode')->required()->maxLength(255),
            DatePicker::make('start_date')->required(),
            DatePicker::make('end_date')->required()->afterOrEqual('start_date'),
            TextInput::make('total_client_amount')->label('Total client amount')->numeric()->required()
                ->minValue(0)->maxValue(99999999.99)->step('0.01')->rules(['decimal:0,2'])->prefix('₹')
                ->live(onBlur: true)->afterStateUpdated(self::updateTrainerAmount(...)),
            TextInput::make('gym_amount')->label('Gym amount / commission')->numeric()->required()->default(0)
                ->minValue(0)->maxValue(99999999.99)->step('0.01')->rules(['decimal:0,2'])->lte('total_client_amount')->prefix('₹')
                ->live(onBlur: true)->afterStateUpdated(self::updateTrainerAmount(...)),
            TextInput::make('trainer_amount')->label('Calculated trainer amount')->prefix('₹')->disabled()->dehydrated(false),
            Select::make('trainer_payment_paid')->label('Trainer settlement status')->options([
                0 => 'Pending',
                1 => 'Paid',
            ])->default(0)->required(),
            Select::make('training_status')->label('Training status')
                ->options(PersonalTrainingMember::TRAINING_STATUSES)->default('pending')->required(),
            TextInput::make('number_of_sessions')->label('Number of sessions')->integer()
                ->minValue(1),
            Textarea::make('remark')->label('Remarks')->maxLength(2000)->columnSpanFull(),
        ]);
    }

    private static function updateTrainerAmount(Get $get, Set $set): void
    {
        $totalClientAmount = $get('total_client_amount');
        $gymAmount = $get('gym_amount');

        if (! is_numeric($totalClientAmount) || ! is_numeric($gymAmount)) {
            return;
        }

        $set('trainer_amount', number_format(max(0, (float) $totalClientAmount - (float) $gymAmount), 2, '.', ''));
    }
}
