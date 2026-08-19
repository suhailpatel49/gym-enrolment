<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Membership overview';

    public function getSubheading(): ?string
    {
        if (auth()->user()?->isAdmin()) {
            return 'Monitor memberships, collections, balances, and upcoming renewals.';
        }

        return 'Monitor membership activity and upcoming renewals.';
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
        ];
    }
}
