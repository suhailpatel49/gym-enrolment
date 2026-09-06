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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class PersonalTrainingMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member_name')->label('Member')->searchable()->sortable(),
                TextColumn::make('trainer.name')->label('Trainer')->searchable()->sortable(),
                TextColumn::make('start_date')->date('d M Y')->sortable(),
                TextColumn::make('end_date')->date('d M Y')->sortable(),
                TextColumn::make('monthly_fee')->money('INR'),
                TextColumn::make('member_payment_paid')->label('Member payment')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Paid' : 'Pending')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
                TextColumn::make('trainer_payment_paid')->label('Trainer payment')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Paid' : 'Pending')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success', 'Upcoming' => 'info', 'Expired' => 'danger', default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('trainer')->relationship('trainer', 'name')->searchable()->preload(),
                SelectFilter::make('status')->options(['active' => 'Active', 'upcoming' => 'Upcoming', 'expired' => 'Expired', 'inactive' => 'Inactive'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value']) {
                        'active' => $query->current(),
                        'upcoming' => $query->where('active', true)->whereDate('start_date', '>', today()->toDateString()),
                        'expired' => $query->where('active', true)->where('end_date', '<', today()->toDateString()),
                        'inactive' => $query->where('active', false),
                        default => $query,
                    }),
                TernaryFilter::make('member_payment_paid')->label('Member payment')->trueLabel('Paid')->falseLabel('Pending'),
                TernaryFilter::make('trainer_payment_paid')->label('Trainer payment')->trueLabel('Paid')->falseLabel('Pending'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('renewOneMonth')->label('Renew one month')->requiresConfirmation()
                    ->authorize('update')
                    ->modalDescription('Extend the end date by one month, or restart an expired subscription from today. Both payments will be reset to pending. If the end date changes before confirmation, refresh and try again.')
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
                Action::make('deactivate')->label('Deactivate')->color('danger')->requiresConfirmation()
                    ->authorize('update')->visible(fn (PersonalTrainingMember $record): bool => $record->active)
                    ->modalDescription('Set this PT membership to inactive. Dates and payment records will be kept.')
                    ->action(function (PersonalTrainingMember $record): void {
                        Gate::authorize('update', $record);
                        $record->update(['active' => false]);
                    }),
            ])
            ->defaultSort('end_date');
    }
}
