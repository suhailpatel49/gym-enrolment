<?php

namespace App\Filament\Widgets;

use App\Models\PersonalTrainingMember;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PersonalTrainingStats extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Personal training';

    protected ?string $description = 'Members manually marked active. Expiry includes today through the next 7 days.';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', PersonalTrainingMember::class) ?? false;
    }

    protected function getStats(): array
    {
        $members = PersonalTrainingMember::query()->current();

        return [
            Stat::make('Active PT members', (clone $members)->count()),
            Stat::make('Trainer payments pending', (clone $members)->where('trainer_payment_paid', false)->count()),
            Stat::make('Subscriptions expiring within 7 days', (clone $members)
                ->whereDate('end_date', '>=', today()->toDateString())
                ->whereDate('end_date', '<=', today()->addDays(7)->toDateString())
                ->count()),
        ];
    }
}
