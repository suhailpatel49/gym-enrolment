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

#[Fillable(['member_name', 'phone', 'trainer_id', 'start_date', 'end_date', 'monthly_fee', 'member_payment_paid', 'trainer_payment_paid', 'active'])]
class PersonalTrainingMember extends Model
{
    /** @use HasFactory<PersonalTrainingMemberFactory> */
    use HasFactory;

    protected $attributes = [
        'active' => true,
        'member_payment_paid' => false,
        'trainer_payment_paid' => false,
    ];

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    #[Scope]
    protected function current(Builder $query): Builder
    {
        return $query->where('active', true)->where('end_date', '>=', today()->toDateString());
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
                'active' => true,
                'member_payment_paid' => false,
                'trainer_payment_paid' => false,
            ]);
    }

    protected function status(): Attribute
    {
        return Attribute::get(fn (): string => ! $this->active ? 'Inactive' : ($this->end_date->lt(today()) ? 'Expired' : 'Active'));
    }

    protected function casts(): array
    {
        return [
            'trainer_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'monthly_fee' => 'decimal:2',
            'active' => 'boolean',
            'member_payment_paid' => 'boolean',
            'trainer_payment_paid' => 'boolean',
        ];
    }
}
