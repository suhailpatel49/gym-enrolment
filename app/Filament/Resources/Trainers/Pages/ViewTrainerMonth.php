<?php

namespace App\Filament\Resources\Trainers\Pages;

use App\Filament\Resources\Trainers\TrainerResource;
use App\Models\PersonalTrainingMember;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Collection;

class ViewTrainerMonth extends ViewRecord
{
    protected static string $resource = TrainerResource::class;

    protected string $view = 'filament.resources.trainers.pages.view-trainer-month';

    public string $month;

    public function mount(int|string $record): void
    {
        $month = request()->route('month');
        abort_unless(is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month), 404);

        $this->month = $month;
        parent::mount($record);
    }

    /** @return Collection<int, PersonalTrainingMember> */
    public function getEntries(): Collection
    {
        return $this->getRecord()->personalTrainingMembersForMonth($this->month);
    }

    public function getTitle(): string
    {
        return now()->createFromFormat('Y-m-d', $this->month.'-01')->format('F Y').' PT details';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Edit trainer'),
        ];
    }
}
