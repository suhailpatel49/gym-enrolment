<?php

namespace Tests\Feature;

use App\Mail\EnrollmentConfirmation;
use App\Models\Enrollment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class EnrollmentDecisionDurabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_queue_insertion_failure_rolls_back_and_retry_creates_one_enrollment_and_job(): void
    {
        $this->useDatabaseQueue();
        DB::statement("CREATE TRIGGER fail_confirmation_queue BEFORE INSERT ON jobs BEGIN SELECT RAISE(FAIL, 'queue unavailable'); END");

        $component = $this->completedForm()->call('review');
        $component->call('approve')
            ->assertHasErrors('review')
            ->assertSee('could not record your enrollment');

        $enrollmentsAfterFailure = Enrollment::query()->count();
        DB::statement('DROP TRIGGER fail_confirmation_queue');

        $component->call('approve');

        $this->assertSame(0, $enrollmentsAfterFailure);
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame(EnrollmentConfirmation::class, $this->queuedPayload()['displayName']);
    }

    public function test_response_loss_and_conflicting_stale_decisions_return_the_original_approved_result_once(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $this->useDatabaseQueue();

        $page = $this->withSession(['tablet_authenticated' => true])
            ->get(route('enrollment.create'))
            ->assertOk();
        $review = $this->livewireUpdate($this->snapshotFrom($page), $this->httpFormUpdates(), 'review')->assertOk();
        $reviewSnapshot = $review->json('components.0.snapshot');

        Livewire::flushState();
        $approved = $this->livewireUpdate($reviewSnapshot, [], 'approve')->assertOk();
        Livewire::flushState();
        $approveRetry = $this->livewireUpdate($reviewSnapshot, [], 'approve')->assertOk();
        Livewire::flushState();
        $rejectRace = $this->livewireUpdate($reviewSnapshot, [], 'reject')->assertOk();

        $reference = Enrollment::query()->sole()->reference_code;

        foreach ([$approved, $approveRetry, $rejectRace] as $response) {
            $response->assertJsonPath('components.0.effects.html', fn (string $html): bool => str_contains($html, 'Enrollment approved')
                && str_contains($html, $reference)
                && ! str_contains($html, 'Enrollment rejected'));
        }

        $this->assertSame('approved', Enrollment::query()->sole()->approval_status);
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame(EnrollmentConfirmation::class, $this->queuedPayload()['displayName']);
    }

    public function test_decision_token_hash_is_a_database_uniqueness_barrier(): void
    {
        $tokenHash = hash('sha256', 'same-reviewed-decision');

        Enrollment::factory()->create(['decision_token_hash' => $tokenHash]);

        $this->expectException(QueryException::class);

        Enrollment::factory()->create(['decision_token_hash' => $tokenHash]);
    }

    private function useDatabaseQueue(): void
    {
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.connection', null);
        config()->set('queue.connections.database.after_commit', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function queuedPayload(): array
    {
        return json_decode(DB::table('jobs')->sole()->payload, true, flags: JSON_THROW_ON_ERROR);
    }

    private function completedForm(): Testable
    {
        return Livewire::test('enrollment-form')
            ->set('email', 'member@example.com')
            ->set('fullName', 'Asha Patel')
            ->set('address', '12 Hill Road')
            ->set('mobileNumber', '9876543210')
            ->set('emergencyContact', '9988776655')
            ->set('dateOfBirth', '1995-05-10')
            ->set('packageMonths', '3')
            ->set('freezingEnabled', false)
            ->set('paymentMode', 'cash')
            ->set('amountPaid', '4500')
            ->set('membershipStartDate', '2026-09-01')
            ->set('hasBalance', false)
            ->set('termsAccepted', true);
    }

    private function snapshotFrom(TestResponse $response): string
    {
        preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);

        $this->assertArrayHasKey(1, $matches, 'The enrollment Livewire snapshot was not rendered.');

        return html_entity_decode($matches[1], ENT_QUOTES);
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function livewireUpdate(string $snapshot, array $updates, string $method): TestResponse
    {
        return $this->withHeader('X-Livewire', 'true')->postJson(route('default-livewire.update'), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => $updates,
                'calls' => [[
                    'path' => '',
                    'method' => $method,
                    'params' => [],
                ]],
            ]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function httpFormUpdates(): array
    {
        return [
            'email' => 'member@example.com',
            'fullName' => 'Asha Patel',
            'address' => '12 Hill Road',
            'mobileNumber' => '9876543210',
            'emergencyContact' => '9988776655',
            'dateOfBirth' => '1995-05-10',
            'packageMonths' => '3',
            'freezingEnabled' => false,
            'paymentMode' => 'cash',
            'amountPaid' => '4500',
            'membershipStartDate' => '2026-09-01',
            'hasBalance' => false,
            'termsAccepted' => true,
        ];
    }
}
