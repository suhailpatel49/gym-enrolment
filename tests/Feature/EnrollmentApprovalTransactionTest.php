<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnrollmentApprovalTransactionTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = tempnam(sys_get_temp_dir(), 'enrollment-approval-');
        config()->set('database.connections.approval_test', array_replace(config('database.connections.sqlite'), [
            'database' => $this->databasePath,
            'url' => null,
            'busy_timeout' => 5000,
        ]));
        DB::setDefaultConnection('approval_test');
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.connection', 'approval_test');
        $this->artisan('migrate', ['--database' => 'approval_test', '--no-interaction' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        DB::purge('approval_test');
        unlink($this->databasePath);
        parent::tearDown();
    }

    public function test_approval_queues_only_after_commit_and_rollback_leaves_the_record_pending(): void
    {
        $actor = User::factory()->create();
        $record = Enrollment::factory()->create(['approval_status' => 'pending']);
        DB::beginTransaction();
        $this->assertTrue($record->approve($actor));
        $this->assertSame(0, DB::table('jobs')->count());
        DB::rollBack();
        $this->assertSame('pending', $record->refresh()->approval_status);
        $this->assertNull($record->approved_by);
        $this->assertNull($record->approved_at);
        $this->assertSame(0, DB::table('jobs')->count());

        $this->assertTrue($record->approve($actor));
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertFalse($record->approve($actor));
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_migration_preserves_legacy_records_and_database_insert_defaults(): void
    {
        $migration = require database_path('migrations/2026_09_06_182408_add_approval_fields_to_enrollments_table.php');
        $migration->down();
        $attributes = Enrollment::factory()->make()->getAttributes();
        unset($attributes['approval_status']);
        $legacyId = DB::table('enrollments')->insertGetId($attributes);
        $migration->up();
        $legacy = Enrollment::findOrFail($legacyId);
        $this->assertSame('approved', $legacy->approval_status);
        $this->assertNull($legacy->approved_by);
        $this->assertNull($legacy->approved_at);

        $attributes['reference_code'] .= '-import';
        $importId = DB::table('enrollments')->insertGetId($attributes);
        $this->assertSame('approved', Enrollment::findOrFail($importId)->approval_status);
    }

    public function test_deleting_an_approver_keeps_membership_and_approval_time(): void
    {
        $actor = User::factory()->staff()->create();
        $record = Enrollment::factory()->create(['approval_status' => 'pending']);
        $record->approve($actor);
        $approvedAt = $record->approved_at;
        $actor->delete();
        $this->assertSame('approved', $record->refresh()->approval_status);
        $this->assertNull($record->approved_by);
        $this->assertTrue($approvedAt->equalTo($record->approved_at));
    }

    public function test_simultaneous_approvals_queue_one_confirmation_and_keep_one_actor(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The concurrent approval test requires pcntl.');
        }

        $actors = [User::factory()->create(), User::factory()->staff()->create()];
        $record = Enrollment::factory()->create(['approval_status' => 'pending']);
        DB::disconnect();
        $children = [];

        try {
            foreach ($actors as $actor) {
                [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid);
                if ($pid === 0) {
                    fclose($parentSocket);
                    stream_set_timeout($childSocket, 10);
                    $waiting = true;
                    DB::listen(function (QueryExecuted $query) use ($childSocket, &$waiting): void {
                        if ($waiting && DB::transactionLevel() > 0 && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'enrollments')) {
                            $waiting = false;
                            fwrite($childSocket, "ready\n");
                            fgets($childSocket);
                        }
                    });
                    try {
                        $approved = $record->approve($actor);
                        fwrite($childSocket, json_encode(['approved' => $approved, 'actor' => $actor->id])."\n");
                    } catch (\Throwable $exception) {
                        fwrite($childSocket, json_encode(['error' => $exception->getMessage()])."\n");
                    }
                    fclose($childSocket);
                    exit(0);
                }
                fclose($childSocket);
                stream_set_timeout($parentSocket, 10);
                $children[] = [$pid, $parentSocket];
            }

            foreach ($children as [$pid, $socket]) {
                $this->assertSame("ready\n", fgets($socket));
            }
            foreach ($children as [$pid, $socket]) {
                fwrite($socket, "approve\n");
            }
            $results = [];
            foreach ($children as [$pid, $socket]) {
                $result = json_decode(fgets($socket), true, flags: JSON_THROW_ON_ERROR);
                $this->assertArrayNotHasKey('error', $result);
                $results[] = $result;
            }
            $winners = array_values(array_filter($results, fn (array $result): bool => $result['approved']));
            $this->assertCount(1, $winners);
            $this->assertSame('approved', $record->refresh()->approval_status);
            $this->assertSame($winners[0]['actor'], $record->approved_by);
            $this->assertNotNull($record->approved_at);
            $this->assertSame(1, DB::table('jobs')->count());
        } finally {
            foreach ($children as [$pid, $socket]) {
                fclose($socket);
                pcntl_waitpid($pid, $status);
            }
        }
    }
}
