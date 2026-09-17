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
    'balance_due_date', 'terms_accepted', 'approval_status', 'approved_at',
    'decision_token_hash',
])]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    public const MEMBERSHIP_FREEZING_CHARGE_DISCLOSURE = 'Membership freezing is charged separately.';

    public const TERMS_ACCEPTANCE_TEXT = 'I accept the gym rules, membership terms, freezing policy, and billing policy.';

    public const MEMBERSHIP_TERMS = [
        'Membership fees are non-refundable under any circumstances.',
        'Membership does not cover medical conditions or accidents.',
        'Additional charges apply for membership freezing and transfer, as per terms and conditions.',
        'Any outstanding membership balance must be cleared or upgraded within 15 days.',
        'Failure to clear dues within 15 days will result in automatic membership downgrade.',
        'Management is not liable for any injury, illness, or loss of life.',
        'Membership can only be transferred to a new member.',
        'The gym is not responsible for loss of belongings or damage.',
    ];

    protected $attributes = ['approval_status' => 'approved'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settleOutstandingBalance(): bool
    {
        if ($this->approval_status !== 'approved' || ! $this->has_balance || (float) $this->remaining_balance <= 0) {
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

    /**
     * @return list<string>
     */
    public function selectedTerms(): array
    {
        return $this->terms_accepted ? self::MEMBERSHIP_TERMS : [];
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
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
