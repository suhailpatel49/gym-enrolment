<?php

namespace App\Models;

use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'reference_code', 'email', 'full_name', 'address', 'mobile_number',
    'emergency_contact', 'date_of_birth', 'package_months', 'membership_package', 'freezing_enabled',
    'freezing_days', 'payment_mode', 'amount_paid', 'membership_start_date',
    'membership_end_date', 'has_balance', 'remaining_balance',
    'balance_due_date', 'terms_accepted',
])]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settleOutstandingBalance(): bool
    {
        if (! $this->has_balance || (float) $this->remaining_balance <= 0) {
            return false;
        }

        $this->update([
            'amount_paid' => number_format((float) $this->amount_paid + (float) $this->remaining_balance, 2, '.', ''),
            'has_balance' => false,
            'remaining_balance' => null,
            'balance_due_date' => null,
        ]);

        return true;
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'date_of_birth' => 'date',
            'package_months' => 'integer',
            'freezing_enabled' => 'boolean',
            'freezing_days' => 'integer',
            'amount_paid' => 'decimal:2',
            'membership_start_date' => 'date',
            'membership_end_date' => 'date',
            'has_balance' => 'boolean',
            'remaining_balance' => 'decimal:2',
            'balance_due_date' => 'date',
            'terms_accepted' => 'boolean',
        ];
    }

    protected function packageLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->membership_package
            ?? $this->package_months.' '.($this->package_months === 1 ? 'Month' : 'Months'));
    }
}
