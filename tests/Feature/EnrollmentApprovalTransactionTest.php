<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\PersonalTrainingMember;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class EnrollmentApprovalTransactionTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = tempnam(sys_get_temp_dir(), 'enrollment-status-');
        config()->set('database.connections.enrollment_status_test', array_replace(config('database.connections.sqlite'), [
            'database' => $this->databasePath,
            'url' => null,
            'busy_timeout' => 5000,
        ]));
        DB::setDefaultConnection('enrollment_status_test');
        $this->artisan('migrate', ['--database' => 'enrollment_status_test', '--no-interaction' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        DB::purge('enrollment_status_test');
        unlink($this->databasePath);
        parent::tearDown();
    }

    public function test_approval_migration_preserves_legacy_records_and_database_insert_defaults(): void
    {
        $migration = require database_path('migrations/2026_09_06_182408_add_approval_fields_to_enrollments_table.php');
        $statusMigration = require database_path('migrations/2026_09_17_070812_add_rejected_status_to_enrollments_table.php');
        $statusMigration->down();
        $migration->down();
        $attributes = Enrollment::factory()->make()->getAttributes();
        unset($attributes['approval_status']);
        $legacyId = DB::table('enrollments')->insertGetId($attributes);
        $migration->up();
        $statusMigration->up();

        $legacy = Enrollment::findOrFail($legacyId);

        $this->assertSame('approved', $legacy->approval_status);
        $this->assertNull($legacy->approved_by);
        $this->assertNull($legacy->approved_at);

        $attributes['reference_code'] .= '-import';
        $importId = DB::table('enrollments')->insertGetId($attributes);

        $this->assertSame('approved', Enrollment::findOrFail($importId)->approval_status);
    }

    public function test_rejected_status_migration_rolls_back_to_pending_and_reapplies(): void
    {
        $record = Enrollment::factory()->create(['approval_status' => 'rejected']);
        $migration = require database_path('migrations/2026_09_17_070812_add_rejected_status_to_enrollments_table.php');

        $migration->down();

        $this->assertSame('pending', $record->refresh()->approval_status);

        $record->update(['approval_status' => 'approved']);
        $migration->up();
        $record->update(['approval_status' => 'rejected']);

        $this->assertSame('rejected', $record->refresh()->approval_status);
    }

    public function test_rejected_status_migration_succeeds_when_no_pending_enrollments_exist(): void
    {
        $record = Enrollment::factory()->create(['approval_status' => 'approved']);
        $migration = require database_path('migrations/2026_09_17_070812_add_rejected_status_to_enrollments_table.php');
        $migration->down();

        $migration->up();

        $this->assertTrue(Schema::hasColumn('enrollments', 'decision_token_hash'));
        $this->assertSame('approved', $record->refresh()->approval_status);
        $record->update(['approval_status' => 'rejected']);
        $this->assertSame('rejected', $record->refresh()->approval_status);
    }

    public function test_rejected_status_migration_fails_before_any_mutation_when_pending_enrollments_exist(): void
    {
        $migration = require database_path('migrations/2026_09_17_070812_add_rejected_status_to_enrollments_table.php');
        $migration->down();
        $pending = Enrollment::factory()->create(['approval_status' => 'pending']);
        $failure = null;

        try {
            $migration->up();
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }

        $this->assertInstanceOf(RuntimeException::class, $failure);
        $this->assertStringContainsString('1 pending enrollment', $failure->getMessage());
        $this->assertStringContainsString('resolve every pending enrollment', $failure->getMessage());
        $this->assertFalse(Schema::hasColumn('enrollments', 'decision_token_hash'));
        $this->assertSame('pending', $pending->refresh()->approval_status);

        $this->expectException(QueryException::class);
        $pending->update(['approval_status' => 'rejected']);
    }

    public function test_combined_release_migrations_roll_back_and_reapply_in_dependency_order(): void
    {
        PersonalTrainingMember::factory()->create();
        $this->artisan('migrate:rollback', [
            '--database' => 'enrollment_status_test',
            '--step' => 8,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('enrollments', 'approval_status'));
        $this->assertFalse(Schema::hasTable('personal_training_members'));
        $this->assertFalse(Schema::hasTable('trainers'));
        $this->assertTrue(Schema::hasTable('enrollments'));
        $this->assertTrue(Schema::hasTable('users'));

        $this->artisan('migrate', ['--database' => 'enrollment_status_test', '--no-interaction' => true])->assertSuccessful();

        $this->assertTrue(Schema::hasColumn('enrollments', 'approval_status'));
        $this->assertTrue(Schema::hasTable('personal_training_members'));
        $this->assertTrue(Schema::hasTable('trainers'));
    }
}
