<?php

namespace Tests\Feature;

use App\Models\PersonalTrainingMember;
use App\Models\Trainer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonalTrainingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_trainers_are_plain_records_with_members_and_no_accounts(): void
    {
        $trainer = Trainer::factory()->create();
        $member = PersonalTrainingMember::factory()->for($trainer)->create();

        $this->assertTrue($trainer->active);
        $this->assertTrue($member->active);
        $this->assertFalse($member->member_payment_paid);
        $this->assertFalse($member->trainer_payment_paid);
        $this->assertTrue($member->trainer->is($trainer));
        $this->assertTrue($trainer->personalTrainingMembers->sole()->is($member));
        $this->assertNotInstanceOf(Authenticatable::class, $trainer);
        $this->assertSame(0, User::query()->count());
        $this->assertFalse(Schema::hasColumn('trainers', 'password'));
        $this->assertFalse(Schema::hasColumn('trainers', 'user_id'));
    }

    #[DataProvider('renewalDates')]
    public function test_renewal_dates_and_payment_reset(string $today, string $start, string $end, string $expectedStart, string $expectedEnd): void
    {
        $this->travelTo(Carbon::parse($today));
        $member = PersonalTrainingMember::factory()->create([
            'start_date' => $start, 'end_date' => $end, 'active' => false,
            'member_payment_paid' => true, 'trainer_payment_paid' => true,
        ]);

        $this->assertTrue($member->renewOneMonth($end));
        $member->refresh();
        $this->assertSame($expectedStart, $member->start_date->toDateString());
        $this->assertSame($expectedEnd, $member->end_date->toDateString());
        $this->assertTrue($member->active);
        $this->assertFalse($member->member_payment_paid);
        $this->assertFalse($member->trainer_payment_paid);
        $this->assertFalse($member->renewOneMonth($end));
        $this->assertSame($expectedEnd, $member->fresh()->end_date->toDateString());
    }

    public static function renewalDates(): array
    {
        return [
            'current month end' => ['2026-01-15', '2026-01-01', '2026-01-31', '2026-01-01', '2026-02-28'],
            'leap year' => ['2028-01-15', '2028-01-01', '2028-01-31', '2028-01-01', '2028-02-29'],
            'expires today' => ['2026-01-31', '2026-01-01', '2026-01-31', '2026-01-01', '2026-02-28'],
            'expired month end' => ['2026-01-31', '2025-12-01', '2025-12-31', '2026-01-31', '2026-02-27'],
            'expired leap year' => ['2028-01-31', '2027-12-01', '2027-12-31', '2028-01-31', '2028-02-28'],
            'expired ordinary day' => ['2026-06-10', '2026-04-01', '2026-04-30', '2026-06-10', '2026-07-09'],
        ];
    }

    public function test_stale_renewal_does_not_reset_newly_recorded_payments(): void
    {
        $member = PersonalTrainingMember::factory()->create(['end_date' => '2026-10-31']);
        $stale = $member->fresh();
        $member->renewOneMonth('2026-10-31');
        $member->update(['member_payment_paid' => true, 'trainer_payment_paid' => true]);

        $this->assertFalse($stale->renewOneMonth('2026-10-31'));
        $this->assertTrue($member->fresh()->member_payment_paid);
        $this->assertTrue($member->fresh()->trainer_payment_paid);
    }

    #[DataProvider('statuses')]
    public function test_status_and_current_scope_agree(bool $active, string $start, string $end, string $status): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 23:59:59'));
        $member = PersonalTrainingMember::factory()->create(['active' => $active, 'start_date' => $start, 'end_date' => $end]);

        $this->assertSame($status, $member->status);
        $this->assertSame($status === 'Active', PersonalTrainingMember::query()->current()->whereKey($member)->exists());
    }

    public static function statuses(): array
    {
        return [
            [true, '2026-06-01', '2026-06-09', 'Completed'],
            [true, '2026-06-01', '2026-06-10', 'Active'],
            [true, '2026-06-01', '2026-06-11', 'Active'],
            [true, '2026-06-10', '2026-06-10', 'Active'],
            [true, '2026-06-11', '2026-06-17', 'Upcoming'],
            [false, '2026-06-11', '2026-06-17', 'Cancelled'],
            [false, '2026-06-01', '2026-06-09', 'Cancelled'],
            [false, '2026-06-01', '2026-06-11', 'Cancelled'],
        ];
    }

    public function test_a_trainer_with_members_cannot_be_deleted_from_the_database(): void
    {
        $member = PersonalTrainingMember::factory()->create();

        $this->expectException(QueryException::class);
        $member->trainer->delete();
    }

    public function test_migrations_roll_back_and_reapply_cleanly(): void
    {
        $this->assertTrue(Schema::hasTable('personal_training_members'));
        $moneyInvariantMigration = require database_path('migrations/2026_09_09_011948_enforce_personal_training_member_money_invariants.php');
        $ledgerMigration = require database_path('migrations/2026_09_09_002809_expand_personal_training_members_for_trainer_ledger.php');
        $membersMigration = require database_path('migrations/2026_09_06_000002_create_personal_training_members_table.php');
        $trainersMigration = require database_path('migrations/2026_09_06_000001_create_trainers_table.php');
        $moneyInvariantMigration->down();
        $ledgerMigration->down();
        $membersMigration->down();
        $trainersMigration->down();
        $this->assertFalse(Schema::hasTable('personal_training_members'));
        $this->assertFalse(Schema::hasTable('trainers'));
        $trainersMigration->up();
        $membersMigration->up();
        $ledgerMigration->up();
        $moneyInvariantMigration->up();
        $this->assertModelExists(PersonalTrainingMember::factory()->create());
    }

    public function test_ledger_migration_preserves_existing_personal_training_amounts(): void
    {
        $moneyInvariantMigration = require database_path('migrations/2026_09_09_011948_enforce_personal_training_member_money_invariants.php');
        $ledgerMigration = require database_path('migrations/2026_09_09_002809_expand_personal_training_members_for_trainer_ledger.php');
        $moneyInvariantMigration->down();
        $ledgerMigration->down();
        $trainer = Trainer::factory()->create();
        DB::table('personal_training_members')->insert([
            'member_name' => 'Existing Client',
            'trainer_id' => $trainer->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'monthly_fee' => '1750.50',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ledgerMigration->up();
        $moneyInvariantMigration->up();

        $entry = PersonalTrainingMember::query()->sole();
        $this->assertSame('Existing Client', $entry->client_name);
        $this->assertSame('1750.50', $entry->total_client_amount);
        $this->assertSame('0.00', $entry->gym_amount);
        $this->assertSame('1750.50', $entry->trainer_amount);
    }
}
