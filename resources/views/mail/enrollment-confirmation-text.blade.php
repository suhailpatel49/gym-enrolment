Incline Fitness - Membership confirmation

Hello {{ $enrollment->full_name }},

Your membership enrollment has been received.

Reference: {{ $enrollment->reference_code }}
Package: {{ $enrollment->package_label }}
Start date: {{ $enrollment->membership_start_date->format('d M Y') }}
End date: {{ $enrollment->membership_end_date->format('d M Y') }}
Freezing option: {{ $enrollment->freezing_enabled ? $enrollment->freezing_days.' days' : 'Not selected' }}
Payment mode: {{ strtoupper($enrollment->payment_mode) }}
Amount paid: INR {{ number_format((float) $enrollment->amount_paid, 2) }}
@if ($enrollment->has_balance)
Remaining balance: INR {{ number_format((float) $enrollment->remaining_balance, 2) }}
Due date: {{ $enrollment->balance_due_date?->format('d M Y') ?? 'Not specified' }}
@else
No outstanding balance is recorded.
@endif
@if ($enrollment->selectedTerms())

Terms accepted:
@foreach ($enrollment->selectedTerms() as $term)
- {{ $term }}
@endforeach
@endif

Please keep this message for your records.
