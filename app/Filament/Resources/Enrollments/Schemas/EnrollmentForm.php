<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Models\Enrollment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('reference_code')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('full_name')
                    ->required(),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('mobile_number')
                    ->required(),
                TextInput::make('emergency_contact'),
                DatePicker::make('date_of_birth')
                    ->required(),
                TextInput::make('membership_package')
                    ->required(),
                Toggle::make('freezing_enabled')
                    ->helperText(Enrollment::MEMBERSHIP_FREEZING_CHARGE_DISCLOSURE)
                    ->required(),
                TextInput::make('freezing_days')
                    ->numeric(),
                TextInput::make('payment_mode')
                    ->required(),
                TextInput::make('amount_paid')
                    ->required()
                    ->numeric(),
                DatePicker::make('membership_start_date')
                    ->required(),
                DatePicker::make('membership_end_date')
                    ->required(),
                Toggle::make('has_balance')
                    ->required(),
                TextInput::make('remaining_balance')
                    ->numeric(),
                DatePicker::make('balance_due_date'),
                Toggle::make('terms_accepted')
                    ->required(),
            ]);
    }
}
