<?php

namespace Tests\Feature;

use App\EnrollmentDecisionRecorder;
use App\Models\Enrollment;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class EnrollmentDecisionConcurrencyTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = tempnam(sys_get_temp_dir(), 'enrollment-race-');
        config()->set('database.connections.enrollment_race_test', array_replace(config('database.connections.sqlite'), [
            'database' => $this->databasePath,
            'url' => null,
            'busy_timeout' => 5000,
        ]));
        DB::setDefaultConnection('enrollment_race_test');
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.connection', 'enrollment_race_test');
        config()->set('queue.connections.database.after_commit', false);
        $this->artisan('migrate', ['--database' => 'enrollment_race_test', '--no-interaction' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        DB::purge('enrollment_race_test');
        unlink($this->databasePath);
        parent::tearDown();
    }

    #[DataProvider('decisionRaceProvider')]
    public function test_concurrent_decisions_commit_one_result_and_at_most_one_confirmation(string $firstDecision, string $secondDecision): void
    {
        $barrierPath = sys_get_temp_dir().'/enrollment-decision-barrier-'.bin2hex(random_bytes(8));
        mkdir($barrierPath);
        $tokenHash = hash('sha256', 'shared-concurrent-review-token');

        try {
            $processes = [
                $this->forkDecision($barrierPath, 'first', 'second', $tokenHash, $firstDecision),
                $this->forkDecision($barrierPath, 'second', 'first', $tokenHash, $secondDecision),
            ];
            $exitCodes = [];

            foreach ($processes as $process) {
                pcntl_waitpid($process, $status);
                $exitCodes[] = pcntl_wexitstatus($status);
            }

            DB::purge('enrollment_race_test');
            $enrollment = Enrollment::query()->sole();

            app(EnrollmentDecisionRecorder::class)->record($tokenHash, 'IF-RACE-0001', $this->attributes(), $firstDecision);
            app(EnrollmentDecisionRecorder::class)->record($tokenHash, 'IF-RACE-0001', $this->attributes(), $secondDecision);

            $this->assertContains(0, $exitCodes);
            $this->assertDatabaseCount('enrollments', 1);
            $this->assertSame($enrollment->approval_status, Enrollment::query()->sole()->approval_status);
            $this->assertDatabaseCount('jobs', $enrollment->approval_status === 'approved' ? 1 : 0);

            foreach (['first', 'second'] as $worker) {
                $errorPath = $barrierPath.'/'.$worker.'.error';

                if (file_exists($errorPath)) {
                    $this->assertStringContainsString('database is locked', file_get_contents($errorPath));
                }
            }
        } finally {
            foreach (glob($barrierPath.'/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($barrierPath);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function decisionRaceProvider(): array
    {
        return [
            'approve and approve' => ['approved', 'approved'],
            'approve and reject' => ['approved', 'rejected'],
        ];
    }

    private function forkDecision(string $barrierPath, string $worker, string $otherWorker, string $tokenHash, string $decision): int
    {
        $processId = pcntl_fork();

        if ($processId === -1) {
            throw new RuntimeException('Unable to fork enrollment decision test process.');
        }

        if ($processId > 0) {
            return $processId;
        }

        DB::purge('enrollment_race_test');
        $reachedLookup = false;

        DB::listen(function (QueryExecuted $query) use ($barrierPath, $worker, $otherWorker, &$reachedLookup): void {
            if ($reachedLookup
                || ! str_contains(strtolower($query->sql), 'select')
                || ! str_contains($query->sql, 'decision_token_hash')) {
                return;
            }

            $reachedLookup = true;
            touch($barrierPath.'/'.$worker.'.ready');
            $deadline = microtime(true) + 5;

            while (! file_exists($barrierPath.'/'.$otherWorker.'.ready')) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Concurrent decision barrier timed out.');
                }

                usleep(10_000);
            }
        });

        try {
            app(EnrollmentDecisionRecorder::class)->record(
                $tokenHash,
                'IF-RACE-0001',
                $this->attributes(),
                $decision,
            );
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($barrierPath.'/'.$worker.'.error', $exception->getMessage());
            exit(2);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(): array
    {
        return [
            'user_id' => null,
            'email' => 'member@example.com',
            'full_name' => 'Asha Patel',
            'address' => '12 Hill Road',
            'mobile_number' => '9876543210',
            'emergency_contact' => '9988776655',
            'date_of_birth' => '1995-05-10',
            'package_months' => 3,
            'membership_package' => '3 Months',
            'freezing_enabled' => false,
            'freezing_days' => null,
            'payment_mode' => 'cash',
            'amount_paid' => '4500',
            'membership_start_date' => '2026-09-01',
            'membership_end_date' => '2026-11-30',
            'has_balance' => false,
            'remaining_balance' => null,
            'balance_due_date' => null,
            'terms_accepted' => true,
        ];
    }
}
