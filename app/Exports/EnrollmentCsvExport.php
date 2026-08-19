<?php

namespace App\Exports;

use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnrollmentCsvExport
{
    public function download(Builder $query): StreamedResponse
    {
        $fileName = 'enrollments-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            $this->writeRow($stream, [
                'Reference',
                'Email',
                'Member Full Name',
                'Address',
                'Mobile Number',
                'Emergency Contact',
                'Date of Birth',
                'Package Months',
                'Membership Package',
                'Freezing Selected',
                'Freezing Days',
                'Payment Mode',
                'Amount Paid',
                'Membership Start Date',
                'Membership End Date',
                'Has Balance',
                'Remaining Balance',
                'Balance Due Date',
                'Terms Accepted',
                'Submitted At',
            ]);

            foreach ((clone $query)->cursor() as $enrollment) {
                $this->writeRow($stream, $this->row($enrollment));
            }

            fclose($stream);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<int, bool|int|string|null>
     */
    private function row(Enrollment $enrollment): array
    {
        return [
            $enrollment->reference_code,
            $enrollment->email,
            $enrollment->full_name,
            $enrollment->address,
            $enrollment->mobile_number,
            $enrollment->emergency_contact,
            $enrollment->date_of_birth?->toDateString(),
            $enrollment->package_months,
            $enrollment->membership_package,
            $enrollment->freezing_enabled ? 'Yes' : 'No',
            $enrollment->freezing_days,
            $enrollment->payment_mode,
            $enrollment->amount_paid,
            $enrollment->membership_start_date->toDateString(),
            $enrollment->membership_end_date->toDateString(),
            $enrollment->has_balance ? 'Yes' : 'No',
            $enrollment->remaining_balance,
            $enrollment->balance_due_date?->toDateString(),
            $enrollment->terms_accepted ? 'Yes' : 'No',
            $enrollment->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  resource  $stream
     * @param  array<int, bool|int|string|null>  $values
     */
    private function writeRow($stream, array $values): void
    {
        fputcsv(
            $stream,
            array_map(fn ($value): string => $this->safeValue($value), $values),
            ',',
            '"',
            '',
            "\r\n",
        );
    }

    private function safeValue(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/u', $value) === 1 ? "'".$value : $value;
    }
}
