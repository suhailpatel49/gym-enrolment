<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Enrollments\Actions\MarkBalancePaidAction;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\Enrollment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OverdueBalances extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Overdue balances')
            ->description('Members with an unpaid balance past its due date.')
            ->query(fn (): Builder => Enrollment::query()
                ->where('approval_status', 'approved')
                ->where('has_balance', true)
                ->where('remaining_balance', '>', 0)
                ->whereDate('balance_due_date', '<', today())
                ->orderBy('balance_due_date'))
            ->columns([
                TextColumn::make('full_name')
                    ->label('Member')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('amount_paid')
                    ->label('Amount paid')
                    ->money('INR')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->weight('bold'),
                TextColumn::make('remaining_balance')
                    ->label('Remaining balance')
                    ->money('INR')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->color('danger')
                    ->weight('bold'),
                TextColumn::make('balance_due_date')
                    ->label('Due date')
                    ->date('d M Y'),
                TextColumn::make('days_overdue')
                    ->label('Overdue')
                    ->getStateUsing(fn (Enrollment $record): string => ((int) $record->balance_due_date->diffInDays(today())).' days')
                    ->badge()
                    ->color('danger'),
                TextColumn::make('mobile_number')
                    ->label('Mobile')
                    ->searchable(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
            ])
            ->recordActions([
                MarkBalancePaidAction::make(),
            ])
            ->recordUrl(fn (Enrollment $record): string => EnrollmentResource::getUrl('view', ['record' => $record]))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No overdue balances')
            ->striped();
    }
}
