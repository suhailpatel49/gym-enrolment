<?php

namespace Tests\Feature;

use App\Mail\EnrollmentConfirmation;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnrollmentApprovalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_generic_records_default_to_approved_without_fabricated_audit_data(): void
    {
        $this->assertSame('approved', (new Enrollment)->approval_status);
        $record = Enrollment::factory()->create()->refresh();
        $this->assertSame('approved', $record->approval_status);
        $this->assertNull($record->approved_by);
        $this->assertNull($record->approved_at);
    }

    /** @return array<int, array{string}> */
    public static function approvers(): array
    {
        return [['admin'], ['staff']];
    }

    #[DataProvider('approvers')]
    public function test_approval_records_the_actor_and_time_and_emails_only_the_member(string $role): void
    {
        Mail::fake();
        $this->travelTo(now()->startOfSecond());
        $actor = User::factory()->create(['role' => $role]);
        $record = Enrollment::factory()->create(['approval_status' => 'pending']);

        $this->assertTrue(Gate::forUser($actor)->allows('approve', $record));
        $this->assertTrue($record->approve($actor));
        $record->refresh();
        $this->assertSame('approved', $record->approval_status);
        $this->assertSame($actor->id, $record->approved_by);
        $this->assertTrue(now()->equalTo($record->approved_at));
        Mail::assertQueued(EnrollmentConfirmation::class, fn (EnrollmentConfirmation $mail): bool => $mail->hasTo($record->email) && count($mail->to) === 1 && $mail->cc === [] && $mail->bcc === []);
        Mail::assertQueuedCount(1);
        if ($role === 'staff') {
            $this->assertFalse(Gate::forUser($actor)->allows('update', $record));
            $this->assertFalse(Gate::forUser($actor)->allows('delete', $record));
        }
    }

    public function test_a_user_without_an_approval_role_cannot_approve(): void
    {
        Mail::fake();
        $actor = User::factory()->make(['role' => null]);
        $record = Enrollment::factory()->create(['approval_status' => 'pending']);
        try {
            $record->approve($actor);
            $this->fail('Approval must be denied.');
        } catch (AuthorizationException) {
            $this->assertSame('pending', $record->refresh()->approval_status);
            Mail::assertNothingQueued();
        }
    }

    public function test_stale_and_repeated_approvals_preserve_the_first_audit_and_queue_once(): void
    {
        Mail::fake();
        $this->travelTo(now()->startOfSecond());
        $firstActor = User::factory()->create();
        $secondActor = User::factory()->staff()->create();
        $first = Enrollment::factory()->create(['approval_status' => 'pending']);
        $stale = $first->fresh();
        $approvedAt = now();

        $this->assertTrue($first->approve($firstActor));
        $this->travel(1)->minute();
        $this->assertFalse($stale->approve($secondActor));
        $this->assertFalse($first->approve($firstActor));
        $this->assertSame($firstActor->id, $first->refresh()->approved_by);
        $this->assertTrue($approvedAt->equalTo($first->approved_at));
        Mail::assertQueued(EnrollmentConfirmation::class, 1);
        Mail::assertQueuedCount(1);
    }
}
