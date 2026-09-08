<?php

namespace App\Models;

use Database\Factories\TrainerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
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
            ->toBase()
            ->selectRaw("strftime('%Y-%m', start_date) as month")
            ->selectRaw('COUNT(CASE WHEN active = 1 THEN 1 END) as entry_count')
            ->selectRaw('COUNT(CASE WHEN active = 1 AND CAST(ROUND(gym_amount * 100) AS INTEGER) = 0 THEN 1 END) as full_payment_count')
            ->selectRaw('COUNT(CASE WHEN active = 1 AND CAST(ROUND(gym_amount * 100) AS INTEGER) > 0 THEN 1 END) as gym_commission_count')
            ->selectRaw('COUNT(CASE WHEN active = 1 AND CAST(trainer_payment_paid AS INTEGER) = 1 THEN 1 END) as paid_count')
            ->selectRaw('COUNT(CASE WHEN active = 1 AND CAST(trainer_payment_paid AS INTEGER) = 0 THEN 1 END) as pending_count')
            ->selectRaw('SUM(CASE WHEN active = 1 THEN CAST(ROUND(total_client_amount * 100) AS INTEGER) ELSE 0 END) as total_client_cents')
            ->selectRaw('SUM(CASE WHEN active = 1 THEN CAST(ROUND(gym_amount * 100) AS INTEGER) ELSE 0 END) as total_gym_cents')
            ->selectRaw('SUM(CASE WHEN active = 1 THEN CAST(ROUND(trainer_amount * 100) AS INTEGER) ELSE 0 END) as total_trainer_cents')
            ->groupBy('month')
            ->orderByDesc('month')
            ->get()
            ->map(fn (object $row): array => [
                'month' => $row->month,
                'label' => now()->createFromFormat('Y-m-d', $row->month.'-01')->format('F Y'),
                'entry_count' => (int) $row->entry_count,
                'full_payment_count' => (int) $row->full_payment_count,
                'gym_commission_count' => (int) $row->gym_commission_count,
                'paid_count' => (int) $row->paid_count,
                'pending_count' => (int) $row->pending_count,
                'total_client_amount' => self::formatCents((int) $row->total_client_cents),
                'total_gym_amount' => self::formatCents((int) $row->total_gym_cents),
                'total_trainer_amount' => self::formatCents((int) $row->total_trainer_cents),
            ]);
    }

    /** @return LengthAwarePaginator<int, PersonalTrainingMember> */
    public function personalTrainingMembersForMonth(string $month): LengthAwarePaginator
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new InvalidArgumentException('Month must use YYYY-MM format.');
        }

        return $this->personalTrainingMembers()
            ->where('start_date', '>=', $month.'-01')
            ->where('start_date', '<', now()->createFromFormat('Y-m-d', $month.'-01')->addMonth()->toDateString())
            ->orderBy('start_date')
            ->orderBy('id')
            ->paginate(12);
    }

    private static function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
