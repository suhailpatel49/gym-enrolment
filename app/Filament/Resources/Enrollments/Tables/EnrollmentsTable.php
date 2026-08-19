<?php

namespace App\Filament\Resources\Enrollments\Tables;

use App\Filament\Resources\Enrollments\Actions\DownloadConfirmationAction;
use App\Filament\Resources\Enrollments\Actions\MarkBalancePaidAction;
use App\Filament\Resources\Enrollments\Actions\SendConfirmationEmailAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;

        return $table
            ->columns([
                TextColumn::make('reference_code')
                    ->label('Reference')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Member')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable($isAdmin)
                    ->visible($isAdmin),
                TextColumn::make('mobile_number')
                    ->label('Mobile')
                    ->searchable($isAdmin)
                    ->visible($isAdmin),
                TextColumn::make('membership_package')
                    ->label('Package')
                    ->badge()
                    ->sortable(),
                TextColumn::make('payment_mode')
                    ->label('Payment')
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->badge()
                    ->visible($isAdmin),
                TextColumn::make('amount_paid')
                    ->label('Paid')
                    ->money('INR')
                    ->sortable()
                    ->visible($isAdmin),
                TextColumn::make('membership_end_date')
                    ->label('Ends')
                    ->date('d M Y')
                    ->sortable(),
                IconColumn::make('has_balance')
                    ->label('Balance due')
                    ->boolean(),
                TextColumn::make('remaining_balance')
                    ->label('Due amount')
                    ->money('INR')
                    ->placeholder('-')
                    ->visible($isAdmin),
                TextColumn::make('balance_due_date')
                    ->label('Balance due date')
                    ->date('d M Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('membership_status')
                    ->label('Membership status')
                    ->options([
                        'active' => 'Active',
                        'ending_7_days' => 'Ending in 7 days',
                        'ending_30_days' => 'Ending in 30 days',
                        'expired' => 'Expired',
                        'upcoming' => 'Upcoming',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['value'] ?? null;

                        return match ($status) {
                            'active' => $query
                                ->whereDate('membership_start_date', '<=', today())
                                ->whereDate('membership_end_date', '>=', today()),
                            'ending_7_days' => $query
                                ->whereDate('membership_start_date', '<=', today())
                                ->whereBetween('membership_end_date', [today(), today()->addDays(7)]),
                            'ending_30_days' => $query
                                ->whereDate('membership_start_date', '<=', today())
                                ->whereBetween('membership_end_date', [today(), today()->addDays(30)]),
                            'expired' => $query->whereDate('membership_end_date', '<', today()),
                            'upcoming' => $query->whereDate('membership_start_date', '>', today()),
                            default => $query,
                        };
                    }),
                SelectFilter::make('package_duration')
                    ->label('Package duration')
                    ->options([
                        '1' => '1 month',
                        '3' => '3 months',
                        '6' => '6 months',
                        '12' => '12 months',
                        '15' => '15 months',
                        'other' => 'Other duration',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $duration = $data['value'] ?? null;

                        if ($duration === 'other') {
                            return $query->where(function (Builder $packageQuery): void {
                                $packageQuery
                                    ->whereNull('package_months')
                                    ->orWhereNotIn('package_months', [1, 3, 6, 12, 15]);
                            });
                        }

                        return filled($duration)
                            ? $query->where('package_months', (int) $duration)
                            : $query;
                    }),
                SelectFilter::make('balance_status')
                    ->label('Balance status')
                    ->options([
                        'outstanding' => 'Outstanding',
                        'overdue' => 'Overdue',
                        'due_7_days' => 'Due in 7 days',
                        'cleared' => 'No balance',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['value'] ?? null;

                        return match ($status) {
                            'outstanding' => $query
                                ->where('has_balance', true)
                                ->where('remaining_balance', '>', 0),
                            'overdue' => $query
                                ->where('has_balance', true)
                                ->where('remaining_balance', '>', 0)
                                ->whereDate('balance_due_date', '<', today()),
                            'due_7_days' => $query
                                ->where('has_balance', true)
                                ->where('remaining_balance', '>', 0)
                                ->whereBetween('balance_due_date', [today(), today()->addDays(7)]),
                            'cleared' => $query->where(function (Builder $balanceQuery): void {
                                $balanceQuery
                                    ->where('has_balance', false)
                                    ->orWhereNull('remaining_balance')
                                    ->orWhere('remaining_balance', '<=', 0);
                            }),
                            default => $query,
                        };
                    }),
                SelectFilter::make('payment_category')
                    ->label('Payment category')
                    ->visible($isAdmin)
                    ->options([
                        'digital' => 'GPay or digital',
                        'cash' => 'Includes cash',
                        'card' => 'Includes card',
                        'not_provided' => 'Not provided',
                        'other' => 'Other method',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::applyPaymentCategory($query, $data['value'] ?? null)),
                TernaryFilter::make('freezing_enabled')
                    ->label('Membership freezing')
                    ->trueLabel('Freezing selected')
                    ->falseLabel('No freezing')
                    ->placeholder('All enrollments'),
                Filter::make('membership_period')
                    ->label('Membership dates')
                    ->schema([
                        DatePicker::make('starts_from')
                            ->label('Starts on or after')
                            ->native(false),
                        DatePicker::make('ends_before')
                            ->label('Ends on or before')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['starts_from'] ?? null, fn (Builder $dateQuery, string $date): Builder => $dateQuery->whereDate('membership_start_date', '>=', $date))
                        ->when($data['ends_before'] ?? null, fn (Builder $dateQuery, string $date): Builder => $dateQuery->whereDate('membership_end_date', '<=', $date)))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        filled($data['starts_from'] ?? null)
                            ? Indicator::make('Starts from '.date('d M Y', strtotime($data['starts_from'])))->removeField('starts_from')
                            : null,
                        filled($data['ends_before'] ?? null)
                            ? Indicator::make('Ends by '.date('d M Y', strtotime($data['ends_before'])))->removeField('ends_before')
                            : null,
                    ]))),
                Filter::make('submitted_period')
                    ->label('Submission dates')
                    ->schema([
                        DatePicker::make('submitted_from')
                            ->label('Submitted from')
                            ->native(false),
                        DatePicker::make('submitted_until')
                            ->label('Submitted until')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['submitted_from'] ?? null, fn (Builder $dateQuery, string $date): Builder => $dateQuery->whereDate('created_at', '>=', $date))
                        ->when($data['submitted_until'] ?? null, fn (Builder $dateQuery, string $date): Builder => $dateQuery->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        filled($data['submitted_from'] ?? null)
                            ? Indicator::make('Submitted from '.date('d M Y', strtotime($data['submitted_from'])))->removeField('submitted_from')
                            : null,
                        filled($data['submitted_until'] ?? null)
                            ? Indicator::make('Submitted until '.date('d M Y', strtotime($data['submitted_until'])))->removeField('submitted_until')
                            : null,
                    ]))),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make(),
                DownloadConfirmationAction::make(),
                SendConfirmationEmailAction::make(),
                MarkBalancePaidAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }

    private static function applyPaymentCategory(Builder $query, ?string $category): Builder
    {
        $digitalPatterns = ['%gpay%', '%g pay%', '%paytm%', '%imps%', '%fund transfer%'];

        if ($category === 'digital') {
            return $query->where(function (Builder $paymentQuery) use ($digitalPatterns): void {
                foreach ($digitalPatterns as $pattern) {
                    $paymentQuery->orWhereRaw('LOWER(payment_mode) LIKE ?', [$pattern]);
                }
            });
        }

        if ($category === 'cash') {
            return $query->whereRaw('LOWER(payment_mode) LIKE ?', ['%cash%']);
        }

        if ($category === 'card') {
            return $query->where(function (Builder $paymentQuery): void {
                $paymentQuery
                    ->whereRaw('LOWER(payment_mode) LIKE ?', ['%card%'])
                    ->orWhereRaw('LOWER(payment_mode) LIKE ?', ['%credit%']);
            });
        }

        if ($category === 'not_provided') {
            return $query->whereRaw('LOWER(payment_mode) = ?', ['not provided']);
        }

        if ($category === 'other') {
            return $query
                ->whereRaw('LOWER(payment_mode) NOT LIKE ?', ['%cash%'])
                ->whereRaw('LOWER(payment_mode) NOT LIKE ?', ['%card%'])
                ->whereRaw('LOWER(payment_mode) NOT LIKE ?', ['%credit%'])
                ->whereRaw('LOWER(payment_mode) != ?', ['not provided'])
                ->where(function (Builder $paymentQuery) use ($digitalPatterns): void {
                    foreach ($digitalPatterns as $pattern) {
                        $paymentQuery->whereRaw('LOWER(payment_mode) NOT LIKE ?', [$pattern]);
                    }
                });
        }

        return $query;
    }
}
