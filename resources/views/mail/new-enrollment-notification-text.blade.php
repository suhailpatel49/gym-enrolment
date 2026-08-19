Incline Fitness - New membership enrollment

Reference: {{ $enrollment->reference_code }}
Member: {{ $enrollment->full_name }}
Email: {{ $enrollment->email }}
Mobile: {{ $enrollment->mobile_number }}
Package: {{ $enrollment->package_label }}
Dates: {{ $enrollment->membership_start_date->format('d M Y') }} to {{ $enrollment->membership_end_date->format('d M Y') }}
Payment: {{ strtoupper($enrollment->payment_mode) }} - INR {{ number_format((float) $enrollment->amount_paid, 2) }}
Freezing option: {{ $enrollment->freezing_enabled ? $enrollment->freezing_days.' days' : 'Not selected' }}
@if ($enrollment->has_balance)
Remaining balance: INR {{ number_format((float) $enrollment->remaining_balance, 2) }}
Due date: {{ $enrollment->balance_due_date?->format('d M Y') ?? 'Not specified' }}
@endif
