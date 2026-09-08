<?php

namespace App\Models;

use Database\Factories\TrainerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;

#[Fillable(['name', 'phone', 'active'])]
class Trainer extends Model
{
    /** @use HasFactory<TrainerFactory> */
    use HasFactory;

    protected $attributes = ['active' => true];

    public function personalTrainingMembers(): HasMany
    {
        return $this->hasMany(PersonalTrainingMember::class);
    }

    /**
     * @return Collection<int, array{
     *     month: string,
     *     label: string,
     *     entry_count: int,
     *     full_payment_count: int,
     *     gym_commission_count: int,
     *     paid_count: int,
     *     pending_count: int,
     *     total_client_amount: string,
     *     total_gym_amount: string,
     *     total_trainer_amount: string
     * }>
     */
    public function monthlyLedger(): Collection
    {
        return $this->personalTrainingMembers()
            ->orderByDesc('start_date')
            ->get()
            ->groupBy(fn (PersonalTrainingMember $entry): string => $entry->start_date->format('Y-m'))
            ->map(function (EloquentCollection $entries, string $month): array {
                $payableEntries = $entries->where('active', true);

                return [
                    'month' => $month,
                    'label' => $entries->first()->start_date->format('F Y'),
                    'entry_count' => $payableEntries->count(),
                    'full_payment_count' => $payableEntries->filter(
                        fn (PersonalTrainingMember $entry): bool => self::amountInCents($entry->gym_amount) === 0
                    )->count(),
                    'gym_commission_count' => $payableEntries->filter(
                        fn (PersonalTrainingMember $entry): bool => self::amountInCents($entry->gym_amount) > 0
                    )->count(),
                    'paid_count' => $payableEntries->where('trainer_payment_paid', true)->count(),
                    'pending_count' => $payableEntries->where('trainer_payment_paid', false)->count(),
                    'total_client_amount' => self::sumAmount($payableEntries, 'total_client_amount'),
                    'total_gym_amount' => self::sumAmount($payableEntries, 'gym_amount'),
                    'total_trainer_amount' => self::sumAmount($payableEntries, 'trainer_amount'),
                ];
            })
            ->values();
    }

    /** @return EloquentCollection<int, PersonalTrainingMember> */
    public function personalTrainingMembersForMonth(string $month): EloquentCollection
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new InvalidArgumentException('Month must use YYYY-MM format.');
        }

        return $this->personalTrainingMembers()
            ->where('start_date', '>=', $month.'-01')
            ->where('start_date', '<', now()->createFromFormat('Y-m-d', $month.'-01')->addMonth()->toDateString())
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();
    }

    private static function amountInCents(string|int|float $amount): int
    {
        return PersonalTrainingMember::amountInCents($amount);
    }

    /** @param EloquentCollection<int, PersonalTrainingMember> $entries */
    private static function sumAmount(EloquentCollection $entries, string $attribute): string
    {
        $cents = $entries->sum(fn (PersonalTrainingMember $entry): int => self::amountInCents($entry->{$attribute}));

        return number_format($cents / 100, 2, '.', '');
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
