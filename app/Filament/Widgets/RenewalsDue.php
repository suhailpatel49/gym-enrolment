<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\Enrollment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RenewalsDue extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;

        return $table
            ->heading('Renewals due')
            ->description('Active memberships ending within the next 30 days.')
            ->query(fn (): Builder => Enrollment::query()
                ->where('approval_status', 'approved')
                ->whereDate('membership_start_date', '<=', today())
                ->whereBetween('membership_end_date', [today(), today()->addDays(30)])
                ->orderBy('membership_end_date'))
            ->columns([
                TextColumn::make('full_name')
                    ->label('Member')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('membership_package')
                    ->label('Package')
                    ->badge(),
                TextColumn::make('membership_end_date')
                    ->label('Membership ends')
                    ->date('d M Y'),
                TextColumn::make('days_remaining')
                    ->label('Time remaining')
                    ->getStateUsing(function (Enrollment $record): string {
                        $days = (int) today()->diffInDays($record->membership_end_date);

                        if ($days === 0) {
                            return 'Ends today';
                        }

                        return $days.' '.($days === 1 ? 'day' : 'days');
                    })
                    ->badge()
                    ->color('warning'),
                TextColumn::make('mobile_number')
                    ->label('Mobile')
                    ->searchable($isAdmin)
                    ->visible($isAdmin),
            ])
            ->recordUrl(fn (Enrollment $record): string => EnrollmentResource::getUrl('view', ['record' => $record]))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No renewals due in 30 days')
            ->striped();
    }
}
