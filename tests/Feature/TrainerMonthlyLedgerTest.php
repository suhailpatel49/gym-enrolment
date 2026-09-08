<?php

namespace Tests\Feature;

use App\Filament\Resources\PersonalTrainingMembers\Pages\CreatePersonalTrainingMember;
use App\Filament\Resources\Trainers\TrainerResource;
use App\Models\PersonalTrainingMember;
use App\Models\Trainer;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrainerMonthlyLedgerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->travelTo(Carbon::parse('2026-06-15'));
    }

    public function test_amount_split_is_calculated_and_invalid_amounts_are_rejected(): void
    {
        $entry = PersonalTrainingMember::factory()->create([
            'total_client_amount' => '2500.50',
            'gym_amount' => '400.25',
            'trainer_amount' => '1.00',
        ]);

        $this->assertSame('2500.50', $entry->total_client_amount);
        $this->assertSame('400.25', $entry->gym_amount);
        $this->assertSame('2100.25', $entry->trainer_amount);

        $this->expectException(ValidationException::class);
        PersonalTrainingMember::factory()->create([
            'total_client_amount' => '100.00',
            'gym_amount' => '100.01',
        ]);
    }

    public function test_amounts_are_converted_to_integer_cents_within_the_decimal_column_limit(): void
    {
        $this->assertSame(9_999_999_999, PersonalTrainingMember::amountInCents('99999999.99'));
        $entry = PersonalTrainingMember::factory()->create([
            'total_client_amount' => '99999999.99',
            'gym_amount' => '0.00',
        ]);
        $this->assertSame('99999999.99', $entry->trainer_amount);

        $this->expectException(ValidationException::class);
        PersonalTrainingMember::amountInCents('100000000.00');
    }

    /** @param array{total_client_amount: string, gym_amount: string, trainer_amount: string} $amounts */
    #[DataProvider('invalidDatabaseAmounts')]
    public function test_database_rejects_invalid_amount_splits(array $amounts): void
    {
        $trainer = Trainer::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('personal_training_members')->insert([
            'client_name' => 'Invalid split',
            'trainer_id' => $trainer->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            ...$amounts,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function invalidDatabaseAmounts(): array
    {
        return [
            'negative total' => [[
                'total_client_amount' => '-0.01',
                'gym_amount' => '0.00',
                'trainer_amount' => '-0.01',
            ]],
            'total above decimal maximum' => [[
                'total_client_amount' => '100000000.00',
                'gym_amount' => '0.00',
                'trainer_amount' => '100000000.00',
            ]],
            'fractional cent' => [[
                'total_client_amount' => '10.001',
                'gym_amount' => '0.00',
                'trainer_amount' => '10.001',
            ]],
            'negative gym amount' => [[
                'total_client_amount' => '10.00',
                'gym_amount' => '-0.01',
                'trainer_amount' => '10.01',
            ]],
            'gym amount above total' => [[
                'total_client_amount' => '10.00',
                'gym_amount' => '10.01',
                'trainer_amount' => '0.00',
            ]],
            'incorrect trainer amount' => [[
                'total_client_amount' => '10.00',
                'gym_amount' => '1.00',
                'trainer_amount' => '8.99',
            ]],
        ];
    }

    public function test_database_rejects_updates_that_break_the_amount_split(): void
    {
        $entry = PersonalTrainingMember::factory()->create([
            'total_client_amount' => '10.00',
            'gym_amount' => '1.00',
        ]);

        $this->expectException(QueryException::class);
        DB::table('personal_training_members')->where('id', $entry->id)->update([
            'trainer_amount' => '8.99',
        ]);
    }

    public function test_training_settlement_and_split_states_are_independent(): void
    {
        $upcoming = PersonalTrainingMember::factory()->create([
            'start_date' => '2026-06-16',
            'end_date' => '2026-07-15',
            'gym_amount' => '0.00',
            'trainer_payment_paid' => true,
        ]);
        $completed = PersonalTrainingMember::factory()->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'gym_amount' => '500.00',
        ]);
        $cancelled = PersonalTrainingMember::factory()->create(['active' => false]);

        $this->assertSame('upcoming', $upcoming->training_status);
        $this->assertSame('paid', $upcoming->trainer_settlement_status);
        $this->assertSame('Trainer RCVD Full Payment', $upcoming->split_classification);
        $this->assertSame('completed', $completed->training_status);
        $this->assertSame('pending', $completed->trainer_settlement_status);
        $this->assertSame('Gym Retained Commission', $completed->split_classification);
        $this->assertSame('cancelled', $cancelled->training_status);
    }

    public function test_monthly_summary_groups_by_start_month_and_excludes_cancelled_entries(): void
    {
        $trainer = Trainer::factory()->create(['name' => 'Alex Trainer']);
        PersonalTrainingMember::factory()->for($trainer)->create([
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
            'total_client_amount' => '1000.00', 'gym_amount' => '0.00', 'trainer_payment_paid' => true,
        ]);
        PersonalTrainingMember::factory()->for($trainer)->create([
            'start_date' => '2026-06-20', 'end_date' => '2026-08-31',
            'total_client_amount' => '2000.00', 'gym_amount' => '500.00', 'trainer_payment_paid' => false,
        ]);
        $cancelled = PersonalTrainingMember::factory()->for($trainer)->create([
            'start_date' => '2026-06-05', 'end_date' => '2026-06-30', 'active' => false,
            'total_client_amount' => '900.00', 'gym_amount' => '100.00',
        ]);
        PersonalTrainingMember::factory()->for($trainer)->create([
            'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
            'total_client_amount' => '700.00', 'gym_amount' => '0.00', 'trainer_payment_paid' => true,
        ]);

        $june = $trainer->monthlyLedger()->firstWhere('month', '2026-06');

        $this->assertSame(2, $june['entry_count']);
        $this->assertSame(1, $june['full_payment_count']);
        $this->assertSame(1, $june['gym_commission_count']);
        $this->assertSame(1, $june['paid_count']);
        $this->assertSame(1, $june['pending_count']);
        $this->assertSame('3000.00', $june['total_client_amount']);
        $this->assertSame('500.00', $june['total_gym_amount']);
        $this->assertSame('2500.00', $june['total_trainer_amount']);
        $this->assertCount(3, $trainer->personalTrainingMembersForMonth('2026-06'));
        $this->assertTrue($trainer->personalTrainingMembersForMonth('2026-06')->contains($cancelled));
        $this->assertCount(2, $trainer->monthlyLedger());
    }

    public function test_monthly_summary_is_grouped_and_summed_by_the_database_without_loading_entry_columns(): void
    {
        $trainer = Trainer::factory()->create();
        PersonalTrainingMember::factory()->for($trainer)->create([
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'total_client_amount' => '0.10',
            'gym_amount' => '0.00',
            'remark' => 'A large private remark must not be selected',
        ]);
        PersonalTrainingMember::factory()->for($trainer)->create([
            'start_date' => '2026-06-02',
            'end_date' => '2026-06-30',
            'total_client_amount' => '0.20',
            'gym_amount' => '0.00',
            'remark' => 'Another large private remark must not be selected',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $june = $trainer->monthlyLedger()->sole();
        $queries = DB::getQueryLog();

        $this->assertSame('0.30', $june['total_client_amount']);
        $this->assertSame('0.30', $june['total_trainer_amount']);
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('group by', strtolower($queries[0]['query']));
        $this->assertStringNotContainsString('select *', strtolower($queries[0]['query']));
        $this->assertStringNotContainsString('remark', strtolower($queries[0]['query']));
    }

    public function test_staff_can_create_a_complete_ledger_entry_and_open_month_details(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $trainer = Trainer::factory()->create(['name' => 'Alex Trainer']);

        Livewire::test(CreatePersonalTrainingMember::class)->fillForm([
            'client_name' => 'Sam Client',
            'phone' => '9876543210',
            'trainer_id' => $trainer->id,
            'payment_mode' => 'UPI',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'total_client_amount' => '2500.00',
            'gym_amount' => '500.00',
            'trainer_payment_paid' => false,
            'active' => true,
            'remark' => 'Morning sessions',
        ])->call('create')->assertHasNoFormErrors();

        $entry = PersonalTrainingMember::query()->sole();
        $this->assertSame('2000.00', $entry->trainer_amount);
        $this->assertSame('UPI', $entry->payment_mode);
        $this->assertSame('Morning sessions', $entry->remark);

        PersonalTrainingMember::factory()->for($trainer)->create([
            'client_name' => 'Cancelled Client',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-20',
            'active' => false,
        ]);
        PersonalTrainingMember::factory()->for($trainer)->create([
            'client_name' => 'July Client',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'trainer_payment_paid' => true,
        ]);

        $this->get(TrainerResource::getUrl('view', ['record' => $trainer]))
            ->assertOk()
            ->assertSee('Monthly PT ledger')
            ->assertSee('Trainer RCVD Full Payment')
            ->assertSee('Gym Retained Commission')
            ->assertSee('June 2026')
            ->assertSee('Payouts pending')
            ->assertSee('Settled')
            ->assertSee('bg-red-50', false)
            ->assertSee('bg-green-50', false)
            ->assertSee('View details');

        $this->get(TrainerResource::getUrl('month', ['record' => $trainer, 'month' => '2026-06']))
            ->assertOk()
            ->assertSee('Sam Client')
            ->assertSee('Alex Trainer')
            ->assertSee('Cancelled Client')
            ->assertSee('Cancelled')
            ->assertSee('Morning sessions')
            ->assertSee('Gym Retained Commission')
            ->assertSee('Pending')
            ->assertSee('Update entry');
    }

    public function test_month_details_render_twelve_entries_per_page_with_navigation(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $trainer = Trainer::factory()->create();

        foreach (range(1, 13) as $number) {
            PersonalTrainingMember::factory()->for($trainer)->create([
                'client_name' => sprintf('June Client %02d', $number),
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-30',
            ]);
        }

        $url = TrainerResource::getUrl('month', ['record' => $trainer, 'month' => '2026-06']);

        $this->get($url)
            ->assertOk()
            ->assertSee('June Client 01')
            ->assertSee('June Client 12')
            ->assertDontSee('June Client 13')
            ->assertSee('Next');

        $this->get($url.'?page=2')
            ->assertOk()
            ->assertDontSee('June Client 01')
            ->assertSee('June Client 13')
            ->assertSee('Previous');
    }
}
