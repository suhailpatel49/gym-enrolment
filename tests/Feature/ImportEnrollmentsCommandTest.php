<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use League\Csv\Writer;
use Tests\TestCase;
use ZipArchive;

class ImportEnrollmentsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $archivePath;

    protected function setUp(): void
    {
        parent::setUp();

        $directory = storage_path('framework/testing');
        File::ensureDirectoryExists($directory);
        $this->archivePath = $directory.'/enrollments-import-test.zip';
    }

    protected function tearDown(): void
    {
        File::delete($this->archivePath);

        parent::tearDown();
    }

    public function test_it_replaces_existing_enrollments_with_csv_rows(): void
    {
        Enrollment::factory()->create();

        $csv = Writer::createFromString();
        $csv->insertOne([
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
        ]);
        $csv->insertOne([
            '2026/02/09 11:25:18 PM GMT+5:30',
            'member@example.com',
            'Asha Patel',
            "12 Hill Road\nMumbai",
            '9876543210',
            '',
            '',
            'Corporate 8 Week Plan',
            'Yes',
            'Cash and Gpay',
            '4500',
            '2026-02-09',
            '2026-04-05',
            'Yes',
            '500',
            '2026-02-15',
            '10',
            'I accept all gym rules, membership terms, freezing policy, and billing policy.',
        ]);

        $zip = new ZipArchive;
        $zip->open($this->archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('Gym Membership Enrollment Form.csv', $csv->toString());
        $zip->close();

        $this->artisan('enrollments:import', [
            'path' => $this->archivePath,
            '--replace' => true,
        ])->assertSuccessful();

        $this->assertSame(1, Enrollment::query()->count());

        $enrollment = Enrollment::query()->firstOrFail();

        $this->assertSame('Asha Patel', $enrollment->full_name);
        $this->assertNull($enrollment->date_of_birth);
        $this->assertSame('Corporate 8 Week Plan', $enrollment->membership_package);
        $this->assertNull($enrollment->package_months);
        $this->assertSame('Cash and Gpay', $enrollment->payment_mode);
        $this->assertTrue($enrollment->freezing_enabled);
        $this->assertSame(10, $enrollment->freezing_days);
        $this->assertTrue($enrollment->has_balance);
        $this->assertSame('500.00', $enrollment->remaining_balance);
        $this->assertSame('2026-02-15', $enrollment->balance_due_date->toDateString());
    }
}
