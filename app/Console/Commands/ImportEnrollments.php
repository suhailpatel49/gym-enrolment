<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

#[Signature('enrollments:import {path : Path to a CSV or ZIP file} {--replace : Delete existing enrollments before importing}')]
#[Description('Import gym enrollment responses from Google Forms CSV data')]
class ImportEnrollments extends Command
{
    /** @var array<int, string> */
    private const REQUIRED_HEADERS = [
        'Timestamp',
        'Username',
        'Member Full Name',
        'Address',
        'Mobile Number',
        'Emergency contact',
        'Date of birth',
        'Membership Package',
        'Do you wish to opt-in for the membership freezing option?',
        'Payment mode',
        'Amount Paid',
        'Membership Start Date',
        'Membership End Date',
        'Is Balance Remaining',
        'Remaining balance',
        'Due date',
        'What is the desired number of freezing days',
        'Terms & Confirmation',
    ];

    public function handle(): int
    {
        try {
            $rows = $this->readRows((string) $this->argument('path'));

            DB::transaction(function () use ($rows): void {
                if ($this->option('replace')) {
                    Enrollment::query()->delete();
                }

                foreach (array_chunk($rows, 25) as $chunk) {
                    Enrollment::query()->upsert(
                        $chunk,
                        ['reference_code'],
                        array_values(array_diff(array_keys($rows[0]), ['reference_code'])),
                    );
                }
            });
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(count($rows).' enrollments imported successfully.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, bool|float|int|string|null>>
     */
    private function readRows(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('The import file does not exist.');
        }

        [$handle, $zip] = $this->openCsv($path);

        try {
            $headers = fgetcsv($handle);

            if ($headers === false) {
                throw new RuntimeException('The CSV file is empty.');
            }

            $headers[0] = Str::remove("\xEF\xBB\xBF", $headers[0]);

            if ($headers !== self::REQUIRED_HEADERS) {
                throw new RuntimeException('The CSV headers do not match the gym enrollment form.');
            }

            $rows = [];
            $rowNumber = 1;

            while (($values = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if (count($values) !== count($headers)) {
                    throw new RuntimeException("CSV row {$rowNumber} has an invalid column count.");
                }

                /** @var array<string, string> $record */
                $record = array_combine($headers, $values);
                $rows[] = $this->mapRecord($record, $rowNumber);
            }
        } finally {
            fclose($handle);
            $zip?->close();
        }

        if ($rows === []) {
            throw new RuntimeException('The CSV file contains no enrollment records.');
        }

        return $rows;
    }

    /**
     * @return array{0: resource, 1: ZipArchive|null}
     */
    private function openCsv(string $path): array
    {
        if (Str::endsWith(Str::lower($path), '.csv')) {
            $handle = fopen($path, 'r');

            if ($handle === false) {
                throw new RuntimeException('The CSV file could not be opened.');
            }

            return [$handle, null];
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('The ZIP file could not be opened.');
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);

            if ($entry !== false && Str::endsWith(Str::lower($entry), '.csv')) {
                $handle = $zip->getStream($entry);

                if ($handle !== false) {
                    return [$handle, $zip];
                }
            }
        }

        $zip->close();

        throw new RuntimeException('The ZIP file does not contain a CSV file.');
    }

    /**
     * @param  array<string, string>  $record
     * @return array<string, bool|float|int|string|null>
     */
    private function mapRecord(array $record, int $rowNumber): array
    {
        $timestamp = CarbonImmutable::parse($record['Timestamp'])->setTimezone(config('app.timezone'));
        $package = trim($record['Membership Package']);
        $hasBalance = Str::lower(trim($record['Is Balance Remaining'])) === 'yes';
        $freezingEnabled = Str::lower(trim($record['Do you wish to opt-in for the membership freezing option?'])) === 'yes';

        return [
            'user_id' => null,
            'reference_code' => 'IF-CSV-'.Str::upper(Str::substr(hash('sha256', implode('|', [
                $record['Timestamp'],
                $record['Username'],
                $record['Mobile Number'],
                $rowNumber,
            ])), 0, 12)),
            'email' => trim($record['Username']),
            'full_name' => trim($record['Member Full Name']),
            'address' => $this->nullableString($record['Address']),
            'mobile_number' => trim($record['Mobile Number']),
            'emergency_contact' => $this->nullableString($record['Emergency contact']),
            'date_of_birth' => $this->nullableDate($record['Date of birth']),
            'package_months' => $this->packageMonths($package),
            'membership_package' => $package,
            'freezing_enabled' => $freezingEnabled,
            'freezing_days' => $freezingEnabled ? $this->nullableInteger($record['What is the desired number of freezing days']) : null,
            'payment_mode' => $this->nullableString($record['Payment mode']) ?? 'Not provided',
            'amount_paid' => $this->decimal($record['Amount Paid']),
            'membership_start_date' => $this->requiredDate($record['Membership Start Date'], $rowNumber, 'Membership Start Date'),
            'membership_end_date' => $this->requiredDate($record['Membership End Date'], $rowNumber, 'Membership End Date'),
            'has_balance' => $hasBalance,
            'remaining_balance' => $hasBalance ? $this->decimal($record['Remaining balance']) : null,
            'balance_due_date' => $hasBalance ? $this->nullableDate($record['Due date']) : null,
            'terms_accepted' => trim($record['Terms & Confirmation']) !== '',
            'created_at' => $timestamp->format('Y-m-d H:i:s'),
            'updated_at' => $timestamp->format('Y-m-d H:i:s'),
        ];
    }

    private function nullableString(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function nullableDate(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : CarbonImmutable::createFromFormat('Y-m-d', $value)->toDateString();
    }

    private function requiredDate(string $value, int $rowNumber, string $field): string
    {
        return $this->nullableDate($value)
            ?? throw new RuntimeException("CSV row {$rowNumber} is missing {$field}.");
    }

    private function decimal(string $value): float
    {
        $normalized = preg_replace('/[^0-9.\-]/', '', $value);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function nullableInteger(string $value): ?int
    {
        $normalized = preg_replace('/[^0-9]/', '', $value);

        return $normalized === '' ? null : (int) $normalized;
    }

    private function packageMonths(string $package): ?int
    {
        if (preg_match('/^(\d+)\s*months?$/i', $package, $matches) === 1) {
            return (int) $matches[1];
        }

        if (ctype_digit($package) && (int) $package <= 24) {
            return (int) $package;
        }

        return null;
    }
}
