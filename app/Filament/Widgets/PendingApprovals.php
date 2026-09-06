<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Enrollments\Actions\ApproveEnrollmentAction;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\Enrollment;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingApprovals extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;

        return $table
            ->heading('Pending approvals')
            ->description('Review submitted enrollments to activate membership and send confirmation.')
            ->query(fn (): Builder => Enrollment::query()
                ->where('approval_status', 'pending')
                ->latest()
                ->latest('id'))
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
                    ->searchable($isAdmin)
                    ->visible($isAdmin),
                TextColumn::make('mobile_number')
                    ->label('Mobile')
                    ->searchable($isAdmin)
                    ->visible($isAdmin),
                TextColumn::make('membership_package')
                    ->label('Package')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y, h:i A'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Enrollment $record): string => EnrollmentResource::getUrl('view', ['record' => $record])),
                ApproveEnrollmentAction::make(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No pending approvals')
            ->emptyStateDescription('New enrollments will appear here when they are submitted for review.')
            ->striped();
    }
}
