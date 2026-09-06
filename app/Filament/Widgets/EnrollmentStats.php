<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\Enrollment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EnrollmentStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Business snapshot';

    protected ?string $description = 'Pending review and current totals from approved enrollments.';

    protected function getStats(): array
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;
        $today = today()->toDateString();
        $monthStart = today()->startOfMonth()->toDateTimeString();
        $monthEnd = today()->endOfMonth()->toDateTimeString();
        $sevenDayLimit = today()->addDays(7)->toDateString();
        $renewalLimit = today()->addDays(30)->toDateString();

        $summaryQuery = Enrollment::query()
            ->where('approval_status', 'approved')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN membership_start_date <= ? AND membership_end_date >= ? THEN 1 ELSE 0 END) as active', [$today, $today])
            ->selectRaw('SUM(CASE WHEN membership_end_date < ? THEN 1 ELSE 0 END) as expired', [$today])
            ->selectRaw('SUM(CASE WHEN membership_start_date > ? THEN 1 ELSE 0 END) as upcoming', [$today])
            ->selectRaw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as new_this_month', [$monthStart, $monthEnd])
            ->selectRaw('SUM(CASE WHEN membership_start_date <= ? AND membership_end_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as renewals_seven_days', [$today, $today, $sevenDayLimit])
            ->selectRaw('SUM(CASE WHEN membership_start_date <= ? AND membership_end_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as renewals_thirty_days', [$today, $today, $renewalLimit])
            ->selectRaw('SUM(CASE WHEN freezing_enabled = 1 THEN 1 ELSE 0 END) as freezing_plans');

        if ($isAdmin) {
            $summaryQuery
                ->selectRaw('SUM(amount_paid) as collected')
                ->selectRaw('SUM(COALESCE(remaining_balance, 0)) as outstanding')
                ->selectRaw('SUM(CASE WHEN has_balance = 1 THEN 1 ELSE 0 END) as balance_accounts')
                ->selectRaw('SUM(CASE WHEN has_balance = 1 AND remaining_balance > 0 AND balance_due_date < ? THEN 1 ELSE 0 END) as overdue_accounts', [$today]);
        }

        $summary = $summaryQuery->first();

        $total = (int) ($summary?->total ?? 0);
        $active = (int) ($summary?->active ?? 0);
        $activeRate = $total > 0 ? round(($active / $total) * 100) : 0;

        $stats = [
            Stat::make('Pending approvals', number_format(Enrollment::query()->where('approval_status', 'pending')->count()))
                ->description('Enrollments awaiting review')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning')
                ->url(EnrollmentResource::getUrl('index')),
            Stat::make('Active memberships', number_format($active))
                ->description($activeRate.'% active · '.number_format($total).' total')
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('success')
                ->url(EnrollmentResource::getUrl('index')),
            Stat::make('New this month', number_format((int) ($summary?->new_this_month ?? 0)))
                ->description('Approved enrollments submitted this month')
                ->descriptionIcon(Heroicon::OutlinedUserPlus)
                ->color('primary'),
            Stat::make('Renewals due', number_format((int) ($summary?->renewals_thirty_days ?? 0)))
                ->description(number_format((int) ($summary?->renewals_seven_days ?? 0)).' in 7 days · '.number_format((int) ($summary?->renewals_thirty_days ?? 0)).' in 30 days')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color('warning'),
            Stat::make('Expired memberships', number_format((int) ($summary?->expired ?? 0)))
                ->description(number_format((int) ($summary?->upcoming ?? 0)).' upcoming · '.number_format((int) ($summary?->freezing_plans ?? 0)).' selected freezing')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('info'),
        ];

        if (! $isAdmin) {
            return $stats;
        }

        return [
            ...$stats,
            Stat::make('Amount recorded as paid', $this->compactCurrency((float) ($summary?->collected ?? 0)))
                ->description('Sum of enrollment payment values')
                ->descriptionIcon(Heroicon::OutlinedCurrencyRupee)
                ->color('success'),
            Stat::make('Outstanding balance', $this->compactCurrency((float) ($summary?->outstanding ?? 0)))
                ->description(number_format((int) ($summary?->balance_accounts ?? 0)).' balances · '.number_format((int) ($summary?->overdue_accounts ?? 0)).' overdue')
                ->descriptionIcon(Heroicon::OutlinedExclamationCircle)
                ->color('danger'),
        ];
    }

    private function compactCurrency(float $amount): string
    {
        if ($amount >= 10_000_000) {
            return '₹'.number_format($amount / 10_000_000, 2).' Cr';
        }

        if ($amount >= 100_000) {
            return '₹'.number_format($amount / 100_000, 2).' L';
        }

        return '₹'.number_format($amount, 0);
    }
}
