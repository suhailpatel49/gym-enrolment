<x-mail-shell title="Membership confirmation" preheader="Your membership enrollment has been received.">
    <tr>
        <td class="email-padding" style="padding:23px 26px; background:#ffffff; border:1px solid #dde4df; border-radius:16px;">
            <p style="margin:0 0 16px; color:#17201c; font-size:15px; line-height:1.6;">Hello {{ $enrollment->full_name }},</p>
            <div style="padding:14px 16px; background:#26332d; border-left:4px solid #c8ff48; color:#ffffff; border-radius:8px;">
                <div style="color:#aebbb4; font-size:10px; letter-spacing:1px; text-transform:uppercase;">Enrollment reference</div>
                <div style="margin-top:4px; font-size:17px; font-weight:700;">{{ $enrollment->reference_code }}</div>
            </div>
        </td>
    </tr>
    <tr><td style="height:12px;"></td></tr>
    <tr>
        <td class="email-padding" style="padding:23px 26px; background:#ffffff; border:1px solid #dde4df; border-radius:16px;">
            <h2 style="margin:0 0 17px; color:#17201c; font-size:17px;">Membership details</h2>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td class="stack-cell" width="50%" style="padding:0 12px 17px 0; vertical-align:top;">
                        <div style="color:#66736c; font-size:10px; letter-spacing:.6px; text-transform:uppercase;">Package</div>
                        <div style="margin-top:4px; color:#17201c; font-size:14px; font-weight:700;">{{ $enrollment->package_label }}</div>
                    </td>
                    <td class="stack-cell" width="50%" style="padding:0 0 17px 12px; vertical-align:top;">
                        <div style="color:#66736c; font-size:10px; letter-spacing:.6px; text-transform:uppercase;">Freezing option</div>
                        <div style="margin-top:4px; color:#17201c; font-size:14px; font-weight:700;">{{ $enrollment->freezing_enabled ? $enrollment->freezing_days.' days' : 'Not selected' }}</div>
                    </td>
                </tr>
                <tr>
                    <td class="stack-cell" width="50%" style="padding:0 12px 0 0; vertical-align:top;">
                        <div style="color:#66736c; font-size:10px; letter-spacing:.6px; text-transform:uppercase;">Start date</div>
                        <div style="margin-top:4px; color:#17201c; font-size:14px; font-weight:700;">{{ $enrollment->membership_start_date->format('d M Y') }}</div>
                    </td>
                    <td class="stack-cell" width="50%" style="padding:0 0 0 12px; vertical-align:top;">
                        <div style="color:#66736c; font-size:10px; letter-spacing:.6px; text-transform:uppercase;">End date</div>
                        <div style="margin-top:4px; color:#17201c; font-size:14px; font-weight:700;">{{ $enrollment->membership_end_date->format('d M Y') }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr><td style="height:12px;"></td></tr>
    <tr>
        <td class="email-padding" style="padding:23px 26px; background:#ffffff; border:1px solid #dde4df; border-radius:16px;">
            <h2 style="margin:0 0 17px; color:#17201c; font-size:17px;">Payment</h2>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td class="stack-cell" width="50%" style="padding:0 12px 0 0; vertical-align:top;">
                        <div style="color:#66736c; font-size:10px; letter-spacing:.6px; text-transform:uppercase;">Payment mode</div>
                        <div style="margin-top:4px; color:#17201c; font-size:14px; font-weight:700;">{{ strtoupper($enrollment->payment_mode) }}</div>
                    </td>
                    <td class="stack-cell" width="50%" style="padding:0 0 0 12px; vertical-align:top;">
                        <div style="color:#66736c; font-size:10px; letter-spacing:.6px; text-transform:uppercase;">Amount paid</div>
                        <div style="margin-top:4px; color:#17201c; font-size:14px; font-weight:700;">INR {{ number_format((float) $enrollment->amount_paid, 2) }}</div>
                    </td>
                </tr>
            </table>

            @if ($enrollment->has_balance)
                <div style="margin-top:18px; padding:14px 16px; background:#fff3e0; border-radius:9px; color:#7c3f00; font-size:13px; line-height:1.55;">
                    <strong>Remaining balance: INR {{ number_format((float) $enrollment->remaining_balance, 2) }}</strong><br>
                    Due on {{ $enrollment->balance_due_date?->format('d M Y') ?? 'Not specified' }}
                </div>
            @else
                <div style="margin-top:18px; padding:14px 16px; background:#eefbd0; border-radius:9px; color:#365314; font-size:13px;">
                    <strong>No outstanding balance is recorded.</strong>
                </div>
            @endif
        </td>
    </tr>
</x-mail-shell>
