<?php

namespace App\Models;

use Database\Factories\PersonalTrainingMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'client_name', 'phone', 'trainer_id', 'payment_mode', 'start_date', 'end_date',
    'total_client_amount', 'gym_amount', 'trainer_amount', 'member_payment_paid',
    'trainer_payment_paid', 'training_status', 'number_of_sessions', 'remark',
])]
class PersonalTrainingMember extends Model
{
    /** @use HasFactory<PersonalTrainingMemberFactory> */
    use HasFactory;

    public const TRAINING_STATUSES = [
        'pending' => 'Pending',
        'active' => 'Active',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $attributes = [
        'training_status' => 'pending',
        'member_payment_paid' => false,
        'trainer_payment_paid' => false,
        'payment_mode' => 'Not provided',
        'gym_amount' => '0.00',
        'trainer_amount' => '0.00',
    ];

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public static function trainingStatusColor(string $trainingStatus): string
    {
        return match ($trainingStatus) {
            'pending' => 'warning',
            'active' => 'success',
            'completed' => 'gray',
            'cancelled' => 'danger',
        };
    }

    #[Scope]
    protected function current(Builder $query): Builder
    {
        return $query->where('training_status', 'active');
    }

    public function renewOneMonth(string $expectedEndDate): bool
    {
        $this->refresh();

        if ($this->end_date->toDateString() !== $expectedEndDate) {
            return false;
        }

        $expired = $this->end_date->lt(today());

        return (bool) static::query()->whereKey($this->getKey())
            ->where('end_date', $this->getRawOriginal('end_date'))
            ->update([
                'start_date' => $expired ? today()->toDateString() : $this->start_date->toDateString(),
                'end_date' => ($expired ? today()->addMonthNoOverflow()->subDay() : $this->end_date->copy()->addMonthNoOverflow())->toDateString(),
                'training_status' => 'active',
                'member_payment_paid' => false,
                'trainer_payment_paid' => false,
            ]);
    }

    protected function status(): Attribute
    {
        return Attribute::get(fn (): string => ucfirst($this->training_status));
    }

    protected function trainerSettlementStatus(): Attribute
    {
        return Attribute::get(fn (): string => $this->trainer_payment_paid ? 'paid' : 'pending');
    }

    protected function splitClassification(): Attribute
    {
        return Attribute::get(fn (): string => self::amountInCents($this->gym_amount) === 0
            ? 'Trainer RCVD Full Payment'
            : 'Gym Retained Commission');
    }

    public static function amountInCents(string|int|float $amount): int
    {
        $amount = (string) $amount;

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw ValidationException::withMessages(['amount' => 'Amounts must be nonnegative with at most two decimal places.']);
        }

        $whole = ltrim($matches[1], '0') ?: '0';
        $fraction = $matches[2] ?? '';

        if (Str::length($whole) > 8) {
            throw ValidationException::withMessages(['amount' => 'Amounts may not exceed 99999999.99.']);
        }

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    protected static function booted(): void
    {
        static::saving(function (PersonalTrainingMember $entry): void {
            $totalClientAmount = self::amountInCents($entry->total_client_amount);
            $gymAmount = self::amountInCents($entry->gym_amount);

            if ($gymAmount > $totalClientAmount) {
                throw ValidationException::withMessages(['gym_amount' => 'The gym amount may not exceed the total client amount.']);
            }

            $entry->trainer_amount = number_format(($totalClientAmount - $gymAmount) / 100, 2, '.', '');
        });
    }

    protected function casts(): array
    {
        return [
            'trainer_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'total_client_amount' => 'decimal:2',
            'gym_amount' => 'decimal:2',
            'trainer_amount' => 'decimal:2',
            'member_payment_paid' => 'boolean',
            'trainer_payment_paid' => 'boolean',
            'number_of_sessions' => 'integer',
        ];
    }
}
