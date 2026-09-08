<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Membership confirmation - {{ $enrollment->reference_code }}</title>
    <style>
        @page { margin: 34px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #171717;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }
        .header {
            padding: 24px 26px;
            border-bottom: 6px solid #F41E1E;
            background: #1D2229;
            color: #FFFFFF;
        }
        .brand {
            color: #FFFFFF;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        h1 {
            margin: 10px 0 5px;
            font-size: 26px;
            line-height: 1.15;
        }
        .subtitle { color: #D8DDE1; }
        .reference {
            margin-top: 18px;
            padding: 12px 14px;
            border-left: 4px solid #F41E1E;
            background: #171717;
        }
        .reference-label {
            color: #D8DDE1;
            font-size: 9px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .reference-value {
            margin-top: 2px;
            font-size: 15px;
            font-weight: bold;
        }
        .section {
            margin-top: 18px;
            padding: 17px 19px;
            border: 1px solid #D8DDE1;
            border-radius: 12px;
        }
        h2 {
            margin: 0 0 12px;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            width: 50%;
            padding: 8px 8px 8px 0;
            vertical-align: top;
        }
        .label {
            color: #6A6A6A;
            font-size: 9px;
            letter-spacing: .5px;
            text-transform: uppercase;
        }
        .value {
            margin-top: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .balance {
            margin-top: 15px;
            padding: 12px 14px;
            border-left: 4px solid #F41E1E;
            border-radius: 4px;
            background: #F8F8F8;
            color: #171717;
        }
        .paid {
            margin-top: 15px;
            padding: 12px 14px;
            border: 1px solid #D8DDE1;
            border-radius: 4px;
            background: #F8F8F8;
            color: #171717;
        }
        .footer {
            margin-top: 22px;
            padding-top: 12px;
            border-top: 1px solid #D8DDE1;
            color: #6A6A6A;
            font-size: 10px;
            text-align: center;
        }
    </style>
</head>
<body data-visual-system="incline">
    <div class="header">
        <div class="brand">Incline Fitness</div>
        <h1>Membership confirmation</h1>
        <div class="subtitle">Your membership enrollment has been received.</div>
        <div class="reference">
            <div class="reference-label">Enrollment reference</div>
            <div class="reference-value">{{ $enrollment->reference_code }}</div>
        </div>
    </div>

    <div class="section">
        <h2>Member details</h2>
        <table>
            <tr>
                <td>
                    <div class="label">Member name</div>
                    <div class="value">{{ $enrollment->full_name }}</div>
                </td>
                <td>
                    <div class="label">Mobile number</div>
                    <div class="value">{{ $enrollment->mobile_number }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="label">Email</div>
                    <div class="value">{{ $enrollment->email }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Membership</h2>
        <table>
            <tr>
                <td>
                    <div class="label">Package</div>
                    <div class="value">{{ $enrollment->package_label }}</div>
                </td>
                <td>
                    <div class="label">Freezing option</div>
                    <div class="value">{{ $enrollment->freezing_enabled ? $enrollment->freezing_days.' days' : 'Not selected' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Start date</div>
                    <div class="value">{{ $enrollment->membership_start_date->format('d M Y') }}</div>
                </td>
                <td>
                    <div class="label">End date</div>
                    <div class="value">{{ $enrollment->membership_end_date->format('d M Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Payment</h2>
        <table>
            <tr>
                <td>
                    <div class="label">Payment mode</div>
                    <div class="value">{{ strtoupper($enrollment->payment_mode) }}</div>
                </td>
                <td>
                    <div class="label">Amount paid</div>
                    <div class="value">INR {{ number_format((float) $enrollment->amount_paid, 2) }}</div>
                </td>
            </tr>
        </table>

        @if ($enrollment->has_balance)
            <div class="balance">
                <strong>Remaining balance: INR {{ number_format((float) $enrollment->remaining_balance, 2) }}</strong><br>
                Due on {{ $enrollment->balance_due_date?->format('d M Y') ?? 'Not specified' }}
            </div>
        @else
            <div class="paid"><strong>No outstanding balance is recorded.</strong></div>
        @endif
    </div>

    @if ($enrollment->selectedTerms())
        <div class="section">
            <h2>Terms accepted</h2>
            <ul style="margin:0; padding-left:18px;">
                @foreach ($enrollment->selectedTerms() as $term)
                    <li>{{ $term }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="footer">
        Generated on {{ now()->format('d M Y, h:i A') }}. Keep this confirmation for your records.
    </div>
</body>
</html>
