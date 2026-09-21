<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonalTrainingStatusMigrationTest extends TestCase
{
    private string $databasePath;

    private string $previousDefaultConnection;

    public static function invalidSessionCounts(): array
    {
        return [
            'zero' => [0],
            'negative integer' => [-1],
            'fractional number' => [1.5],
            'fractional numeric string' => ['1.5'],
            'text' => ['sessions'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDefaultConnection = config('database.default');
        $databasePath = tempnam(sys_get_temp_dir(), 'pt-status-migration-');
        $this->assertNotFalse($databasePath);
        $this->databasePath = $databasePath;

        config([
            'database.default' => 'pt_status_migration',
            'database.connections.pt_status_migration' => [
                'driver' => 'sqlite',
                'database' => $this->databasePath,
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('pt_status_migration');

        Schema::create('personal_training_members', function (Blueprint $table): void {
            $table->id();
            $table->string('client_name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('active')->default(true);
            $table->text('remark')->nullable();
            $table->index(['active', 'end_date']);
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('pt_status_migration');
        DB::purge('pt_status_migration');
        config(['database.default' => $this->previousDefaultConnection]);
        unlink($this->databasePath);

        parent::tearDown();
    }

    public function test_migration_backfills_all_manual_statuses_and_rolls_back_safely(): void
    {
        $this->travelTo('2026-06-10 12:00:00');
        DB::table('personal_training_members')->insert([
            ['client_name' => 'Cancelled Client', 'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'active' => false, 'remark' => 'Keep cancelled'],
            ['client_name' => 'Pending Client', 'start_date' => '2026-06-11', 'end_date' => '2026-07-10', 'active' => true, 'remark' => 'Keep pending'],
            ['client_name' => 'Completed Client', 'start_date' => '2026-05-01', 'end_date' => '2026-06-09', 'active' => true, 'remark' => 'Keep completed'],
            ['client_name' => 'Active Client', 'start_date' => '2026-06-10 00:00:00', 'end_date' => '2026-06-10 00:00:00', 'active' => true, 'remark' => 'Keep active'],
        ]);

        $migration = require database_path('migrations/2026_09_21_100139_add_training_status_to_personal_training_members_table.php');
        $migration->up();

        $this->assertSame([
            'Active Client' => 'active',
            'Cancelled Client' => 'cancelled',
            'Completed Client' => 'completed',
            'Pending Client' => 'pending',
        ], DB::table('personal_training_members')->orderBy('client_name')->pluck('training_status', 'client_name')->all());
        $this->assertSame([
            'Active Client' => 'Keep active',
            'Cancelled Client' => 'Keep cancelled',
            'Completed Client' => 'Keep completed',
            'Pending Client' => 'Keep pending',
        ], DB::table('personal_training_members')->orderBy('client_name')->pluck('remark', 'client_name')->all());
        $this->assertFalse(Schema::hasColumn('personal_training_members', 'active'));

        $invalidStatusFailure = null;
        try {
            DB::table('personal_training_members')->where('client_name', 'Active Client')->update(['training_status' => 'paused']);
        } catch (QueryException $exception) {
            $invalidStatusFailure = $exception;
        }
        $this->assertInstanceOf(QueryException::class, $invalidStatusFailure);
        $this->assertSame('active', DB::table('personal_training_members')->where('client_name', 'Active Client')->value('training_status'));

        $sessionsMigration = require database_path('migrations/2026_09_21_101122_add_number_of_sessions_to_personal_training_members_table.php');
        $sessionsMigration->up();
        $this->assertTrue(Schema::hasColumn('personal_training_members', 'number_of_sessions'));
        $this->assertSame(4, DB::table('personal_training_members')->whereNull('number_of_sessions')->count());
        DB::table('personal_training_members')->where('client_name', 'Active Client')->update(['number_of_sessions' => 37]);
        $this->assertSame(37, DB::table('personal_training_members')->where('client_name', 'Active Client')->value('number_of_sessions'));

        $sessionsMigration->down();
        $this->assertFalse(Schema::hasColumn('personal_training_members', 'number_of_sessions'));
        $this->assertSame('Keep active', DB::table('personal_training_members')->where('client_name', 'Active Client')->value('remark'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('personal_training_members', 'training_status'));
        $this->assertSame([
            'Active Client' => 1,
            'Cancelled Client' => 0,
            'Completed Client' => 1,
            'Pending Client' => 1,
        ], DB::table('personal_training_members')->orderBy('client_name')->pluck('active', 'client_name')->all());
        $this->assertSame(4, DB::table('personal_training_members')->count());
    }

    #[DataProvider('invalidSessionCounts')]
    public function test_sessions_migration_rejects_invalid_values_on_insert(int|float|string $numberOfSessions): void
    {
        $migration = require database_path('migrations/2026_09_21_101122_add_number_of_sessions_to_personal_training_members_table.php');
        $migration->up();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid personal training number of sessions');

        DB::table('personal_training_members')->insert([
            'client_name' => 'Invalid insert',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'number_of_sessions' => $numberOfSessions,
        ]);
    }

    #[DataProvider('invalidSessionCounts')]
    public function test_sessions_migration_rejects_invalid_values_on_update(int|float|string $numberOfSessions): void
    {
        $migration = require database_path('migrations/2026_09_21_101122_add_number_of_sessions_to_personal_training_members_table.php');
        $migration->up();
        $memberId = DB::table('personal_training_members')->insertGetId([
            'client_name' => 'Invalid update',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'number_of_sessions' => null,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid personal training number of sessions');

        DB::table('personal_training_members')->where('id', $memberId)->update([
            'number_of_sessions' => $numberOfSessions,
        ]);
    }

    public function test_sessions_migration_accepts_null_and_positive_integers_and_removes_triggers_before_rollback(): void
    {
        $migration = require database_path('migrations/2026_09_21_101122_add_number_of_sessions_to_personal_training_members_table.php');
        $migration->up();

        foreach ([null, 1, 10_001] as $index => $numberOfSessions) {
            $memberId = DB::table('personal_training_members')->insertGetId([
                'client_name' => "Valid sessions {$index}",
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-30',
                'number_of_sessions' => $numberOfSessions,
            ]);

            DB::table('personal_training_members')->where('id', $memberId)->update([
                'number_of_sessions' => $numberOfSessions,
            ]);
        }

        $this->assertSame([null, 1, 10_001], DB::table('personal_training_members')->orderBy('id')->pluck('number_of_sessions')->all());
        $this->assertSame(2, DB::table('sqlite_master')->where('type', 'trigger')->where('name', 'like', 'personal_training_members_sessions_%')->count());

        $migration->down();

        $this->assertSame(0, DB::table('sqlite_master')->where('type', 'trigger')->where('name', 'like', 'personal_training_members_sessions_%')->count());
        $this->assertFalse(Schema::hasColumn('personal_training_members', 'number_of_sessions'));
    }
}
