<?php

namespace App\Filament\Resources\Trainers\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrainersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone')->placeholder('—'),
                IconColumn::make('active')->boolean(),
                TextColumn::make('personal_training_members_count')->label('Active PT members')
                    ->counts(['personalTrainingMembers' => fn (Builder $query): Builder => $query->current()]),
            ])
            ->filters([TernaryFilter::make('active')])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->defaultSort('name');
    }
}
