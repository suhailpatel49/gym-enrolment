<?php

namespace App\Filament\Resources\PersonalTrainingMembers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PersonalTrainingMemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('client_name')->label('Client name'),
            TextEntry::make('phone')->placeholder('—'),
            TextEntry::make('trainer.name')->label('Trainer'),
            TextEntry::make('payment_mode')->label('Payment mode'),
            TextEntry::make('start_date')->date('d M Y'),
            TextEntry::make('end_date')->date('d M Y'),
            TextEntry::make('total_client_amount')->label('Total client amount')->money('INR'),
            TextEntry::make('gym_amount')->label('Gym amount / commission')->money('INR'),
            TextEntry::make('trainer_amount')->label('Trainer amount')->money('INR'),
            TextEntry::make('split_classification')->label('Split')->badge()
                ->color(fn (string $state): string => $state === 'Trainer RCVD Full Payment' ? 'info' : 'gray'),
            TextEntry::make('training_status')->label('Training status')->badge()
                ->formatStateUsing(fn (string $state): string => ucfirst($state))
                ->color(fn (string $state): string => match ($state) {
                    'active' => 'success', 'upcoming' => 'info', 'completed' => 'gray', 'cancelled' => 'danger',
                }),
            TextEntry::make('trainer_settlement_status')->label('Trainer settlement')->badge()
                ->formatStateUsing(fn (string $state): string => ucfirst($state))
                ->color(fn (string $state): string => $state === 'paid' ? 'success' : 'danger'),
            TextEntry::make('remark')->placeholder('—')->columnSpanFull(),
        ]);
    }
}
