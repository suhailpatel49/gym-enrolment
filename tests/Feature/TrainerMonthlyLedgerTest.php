<?php

namespace Tests\Feature;

use App\Filament\Resources\PersonalTrainingMembers\Pages\CreatePersonalTrainingMember;
use App\Filament\Resources\Trainers\TrainerResource;
use App\Models\PersonalTrainingMember;
use App\Models\Trainer;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
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
}
