<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Membership overview';

    public function getSubheading(): ?string
    {
        if (auth()->user()?->isAdmin()) {
            return 'Monitor personal training, memberships, collections, balances, and renewals.';
        }

        return 'Monitor personal training, memberships, and renewals.';
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
        ];
    }
}
