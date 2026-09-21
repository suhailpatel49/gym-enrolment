<?php

namespace App\Filament\Resources\PersonalTrainingMembers\Tables;

use App\Models\PersonalTrainingMember;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class PersonalTrainingMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client_name')->label('Client')->searchable()->sortable(),
                TextColumn::make('trainer.name')->label('Trainer')->searchable()->sortable(),
                TextColumn::make('start_date')->date('d M Y')->sortable(),
                TextColumn::make('end_date')->date('d M Y')->sortable(),
                TextColumn::make('number_of_sessions')->label('Number of sessions')->placeholder('—')->toggleable(),
                TextColumn::make('total_client_amount')->label('Client amount')->money('INR'),
                TextColumn::make('gym_amount')->label('Gym amount')->money('INR'),
                TextColumn::make('trainer_amount')->label('Trainer amount')->money('INR'),
                TextColumn::make('split_classification')->label('Split')->badge(),
                TextColumn::make('trainer_payment_paid')->label('Trainer settlement')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Paid' : 'Pending')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('training_status')->label('Training status')->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => PersonalTrainingMember::trainingStatusColor($state)),
            ])
            ->filters([
                SelectFilter::make('trainer')->relationship('trainer', 'name')->searchable()->preload(),
                SelectFilter::make('status')->options(PersonalTrainingMember::TRAINING_STATUSES)->attribute('training_status'),
                TernaryFilter::make('trainer_payment_paid')->label('Trainer settlement')->trueLabel('Paid')->falseLabel('Pending'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('renewOneMonth')->label('Renew one month')->requiresConfirmation()
                    ->authorize('update')
                    ->modalDescription('Extend the end date by one month, or restart from today when the end date has passed. Training status will be set to active and trainer settlement will be reset to pending. If the end date changes before confirmation, refresh and try again.')
                    ->modalSubmitActionLabel('Renew one month')
                    ->mountUsing(function (PersonalTrainingMember $record, ListRecords $livewire): void {
                        $livewire->mergeMountedActionArguments(['end_date' => $record->end_date->toDateString()]);
                    })
                    ->action(function (PersonalTrainingMember $record, array $arguments, Action $action): void {
                        Gate::authorize('update', $record);
                        if (! $record->renewOneMonth($arguments['end_date'] ?? '')) {
                            $action->cancel();
                        }
                    }),
                Action::make('deactivate')->label('Cancel training')->color('danger')->requiresConfirmation()
                    ->authorize('update')->visible(fn (PersonalTrainingMember $record): bool => $record->training_status !== 'cancelled')
                    ->modalDescription('Cancel this PT entry. Dates and financial records will be kept, and it will be excluded from payable summaries.')
                    ->action(function (PersonalTrainingMember $record): void {
                        Gate::authorize('update', $record);
                        $record->update(['training_status' => 'cancelled']);
                    }),
            ])
            ->defaultSort('end_date');
    }
}
