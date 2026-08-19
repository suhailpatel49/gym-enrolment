<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Models\Enrollment;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EnrollmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;

        return $schema
            ->components([
                TextEntry::make('reference_code')
                    ->label('Reference')
                    ->copyable(),
                TextEntry::make('email')
                    ->label('Email address')
                    ->visible($isAdmin),
                TextEntry::make('full_name'),
                TextEntry::make('address')
                    ->placeholder('-')
                    ->columnSpanFull()
                    ->visible($isAdmin),
                TextEntry::make('mobile_number')
                    ->visible($isAdmin),
                TextEntry::make('emergency_contact')
                    ->placeholder('-')
                    ->visible($isAdmin),
                TextEntry::make('date_of_birth')
                    ->date()
                    ->visible($isAdmin),
                TextEntry::make('membership_package')
                    ->label('Package')
                    ->badge(),
                IconEntry::make('freezing_enabled')
                    ->boolean(),
                TextEntry::make('freezing_days')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('payment_mode')
                    ->visible($isAdmin),
                TextEntry::make('amount_paid')
                    ->label('Amount paid')
                    ->money('INR')
                    ->visible(fn (Enrollment $record): bool => $isAdmin || $record->has_balance),
                TextEntry::make('membership_start_date')
                    ->date('d M Y'),
                TextEntry::make('membership_end_date')
                    ->date('d M Y'),
                IconEntry::make('has_balance')
                    ->label('Balance due')
                    ->boolean(),
                TextEntry::make('remaining_balance')
                    ->label('Remaining balance')
                    ->money('INR')
                    ->placeholder('-')
                    ->visible(fn (Enrollment $record): bool => $isAdmin || $record->has_balance),
                TextEntry::make('balance_due_date')
                    ->label('Balance due date')
                    ->date('d M Y')
                    ->placeholder('-'),
                IconEntry::make('terms_accepted')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
