<?php

namespace App;

use App\Mail\EnrollmentConfirmation;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use LogicException;

class EnrollmentDecisionRecorder
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(string $decisionTokenHash, string $referenceCode, array $attributes, string $decision): Enrollment
    {
        $this->assertAtomicDatabaseQueue();

        return DB::transaction(function () use ($decisionTokenHash, $referenceCode, $attributes, $decision): Enrollment {
            $enrollment = Enrollment::query()->firstOrCreate(
                ['decision_token_hash' => $decisionTokenHash],
                [
                    ...$attributes,
                    'reference_code' => $referenceCode,
                    'approval_status' => $decision,
                    'approved_at' => $decision === 'approved' ? now() : null,
                ],
            );

            if ($enrollment->wasRecentlyCreated && $decision === 'approved') {
                Mail::to($enrollment->email)->queue(
                    (new EnrollmentConfirmation($enrollment))->onConnection('database')->beforeCommit(),
                );
            }

            return $enrollment;
        });
    }

    private function assertAtomicDatabaseQueue(): void
    {
        $enrollmentConnection = (new Enrollment)->getConnectionName() ?? config('database.default');
        $queueConnection = config('queue.connections.database.connection') ?? config('database.default');

        if (config('queue.connections.database.driver') !== 'database' || $queueConnection !== $enrollmentConnection) {
            throw new LogicException('Enrollment confirmation queue must use the enrollment database connection.');
        }
    }
}
