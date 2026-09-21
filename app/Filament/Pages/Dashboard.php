<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OverdueBalances;
use App\Filament\Widgets\RenewalsDue;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Membership overview';

    public function getSubheading(): ?string
    {
        return 'Review overdue balances and upcoming membership renewals.';
    }

    public function getWidgets(): array
    {
        return [
            OverdueBalances::class,
            RenewalsDue::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
        ];
    }
}
