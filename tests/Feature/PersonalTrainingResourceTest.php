<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\PersonalTrainingMembers\Pages\CreatePersonalTrainingMember;
use App\Filament\Resources\PersonalTrainingMembers\Pages\EditPersonalTrainingMember;
use App\Filament\Resources\PersonalTrainingMembers\Pages\ListPersonalTrainingMembers;
use App\Filament\Resources\PersonalTrainingMembers\PersonalTrainingMemberResource;
use App\Filament\Resources\Trainers\Pages\CreateTrainer;
use App\Filament\Resources\Trainers\Pages\EditTrainer;
use App\Filament\Resources\Trainers\Pages\ListTrainers;
use App\Filament\Resources\Trainers\TrainerResource;
use App\Models\PersonalTrainingMember;
use App\Models\Trainer;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonalTrainingResourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->travelTo(Carbon::parse('2026-06-10'));
    }

    public static function roles(): array
    {
        return [[UserRole::Admin], [UserRole::Staff]];
    }

    #[DataProvider('roles')]
    public function test_admin_and_staff_can_create_view_and_edit_both_resources(UserRole $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        Mail::fake();
        Notification::fake();
        Livewire::test(CreateTrainer::class)->fillForm(['name' => 'Alex Trainer', 'phone' => null])
            ->call('create')->assertHasNoFormErrors()->assertNotNotified();
        $trainer = Trainer::query()->sole();
        Livewire::test(EditTrainer::class, ['record' => $trainer->id])->fillForm(['phone' => '123456789', 'active' => false])
            ->call('save')->assertHasNoFormErrors()->assertNotNotified();
        $this->assertFalse($trainer->fresh()->active);
        $this->assertSame('123456789', $trainer->fresh()->phone);

        Livewire::test(CreatePersonalTrainingMember::class)->fillForm([
            'client_name' => 'PT Client', 'phone' => '987654321', 'trainer_id' => $trainer->id, 'payment_mode' => 'Cash',
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
            'total_client_amount' => '2500.50', 'gym_amount' => '500.25',
        ])->call('create')->assertHasNoFormErrors()->assertNotNotified();
        $member = PersonalTrainingMember::query()->sole();
        $this->assertTrue($member->trainer->is($trainer));
        $this->assertSame('2500.50', $member->total_client_amount);
        $this->assertSame('2000.25', $member->trainer_amount);
        $this->assertFalse($member->trainer_payment_paid);
        Livewire::test(EditPersonalTrainingMember::class, ['record' => $member->id])
            ->fillForm([
                'client_name' => 'Updated Client',
                'payment_mode' => 'Card',
                'end_date' => '2026-07-15',
                'gym_amount' => '0.00',
                'trainer_payment_paid' => true,
                'remark' => 'Updated arrangement',
            ])
            ->call('save')->assertHasNoFormErrors()->assertNotNotified();
        $member->refresh();
        $this->assertSame('Updated Client', $member->client_name);
        $this->assertSame('Card', $member->payment_mode);
        $this->assertSame('2026-07-15', $member->end_date->toDateString());
        $this->assertSame('2500.50', $member->trainer_amount);
        $this->assertTrue($member->trainer_payment_paid);
        $this->assertSame('Updated arrangement', $member->remark);

        foreach ([[TrainerResource::class, $trainer], [PersonalTrainingMemberResource::class, $member]] as [$resource, $record]) {
            foreach (['index', 'create', 'view', 'edit'] as $page) {
                $this->get($resource::getUrl($page, ['record' => $record]))->assertOk();
            }
            $this->assertFalse(Gate::allows('delete', $record));
            $this->assertFalse(Gate::allows('deleteAny', $record::class));
            $this->assertFalse(Gate::allows('forceDelete', $record));
        }
        $this->assertSame(1, User::query()->count());
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_guests_cannot_access_resources_or_policies(): void
    {
        $trainer = Trainer::factory()->create();
        $member = PersonalTrainingMember::factory()->for($trainer)->create();
        foreach ([[TrainerResource::class, $trainer], [PersonalTrainingMemberResource::class, $member]] as [$resource, $record]) {
            foreach (['index', 'create', 'view', 'edit'] as $page) {
                $this->get($resource::getUrl($page, ['record' => $record]))->assertRedirect('/admin/login');
            }
            foreach (['view', 'update', 'delete'] as $ability) {
                $this->assertFalse(Gate::allows($ability, $record));
            }
        }
    }

    public function test_required_fields_and_invalid_values_are_rejected_on_create_and_edit(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        Livewire::test(CreateTrainer::class)->fillForm(['name' => ''])->call('create')->assertHasFormErrors(['name' => 'required']);
        $trainer = Trainer::factory()->create();
        Livewire::test(EditTrainer::class, ['record' => $trainer->id])->fillForm(['name' => str_repeat('x', 256)])
            ->call('save')->assertHasFormErrors(['name' => 'max']);
        Livewire::test(CreatePersonalTrainingMember::class)->fillForm([
            'client_name' => '', 'phone' => null, 'trainer_id' => null, 'payment_mode' => null, 'start_date' => null,
            'end_date' => null, 'total_client_amount' => null,
        ])->call('create')->assertHasFormErrors(['client_name', 'phone', 'trainer_id', 'payment_mode', 'start_date', 'end_date', 'total_client_amount']);
        $member = PersonalTrainingMember::factory()->for($trainer)->create();
        foreach ([[CreatePersonalTrainingMember::class, [], 'create'], [EditPersonalTrainingMember::class, ['record' => $member->id], 'save']] as [$page, $parameters, $method]) {
            Livewire::test($page, $parameters)->fillForm([
                'client_name' => 'Client', 'trainer_id' => 999999, 'payment_mode' => 'Cash', 'start_date' => '2026-06-10',
                'end_date' => '2026-06-09', 'total_client_amount' => -1, 'gym_amount' => -1,
            ])->call($method)->assertHasFormErrors(['trainer_id', 'end_date', 'total_client_amount', 'gym_amount']);
            Livewire::test($page, $parameters)->fillForm([
                'client_name' => 'Client', 'trainer_id' => $trainer->id, 'payment_mode' => 'Cash', 'start_date' => '2026-06-10',
                'end_date' => '2026-06-10', 'total_client_amount' => '12.345', 'gym_amount' => '13.00',
            ])->call($method)->assertHasFormErrors(['total_client_amount', 'gym_amount']);
        }
        $this->assertSame(1, PersonalTrainingMember::query()->count());
    }

    #[DataProvider('roles')]
    public function test_row_actions_confirm_renew_and_deactivate_without_delete(UserRole $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $member = PersonalTrainingMember::factory()->create([
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
            'member_payment_paid' => true, 'trainer_payment_paid' => true,
        ]);
        $renew = TestAction::make('renewOneMonth')->table($member);
        $page = Livewire::test(ListPersonalTrainingMembers::class)->mountAction($renew);
        $this->assertSame('2026-06-30', $member->fresh()->end_date->toDateString());
        $page->callMountedAction();
        $this->assertSame('2026-07-30', $member->fresh()->end_date->toDateString());
        $this->assertFalse($member->fresh()->member_payment_paid);
        $this->assertFalse($member->fresh()->trainer_payment_paid);
        $beforeDeactivation = $member->fresh()->only(['start_date', 'end_date', 'total_client_amount', 'gym_amount', 'trainer_amount', 'trainer_payment_paid']);
        $deactivate = TestAction::make('deactivate')->table($member);
        $duplicateDeactivation = Livewire::test(ListPersonalTrainingMembers::class)->mountAction($deactivate);
        $page->mountAction($deactivate);
        $this->assertTrue($member->fresh()->active);
        $page->callMountedAction();
        $this->assertFalse($member->fresh()->active);
        $duplicateDeactivation->callMountedAction();
        $this->assertFalse($member->fresh()->active);
        $this->assertEquals($beforeDeactivation, $member->fresh()->only(array_keys($beforeDeactivation)));
        $page->assertActionHidden($deactivate)->assertActionDoesNotExist(TestAction::make('delete')->table($member));
        Livewire::test(ListTrainers::class)->assertActionDoesNotExist(TestAction::make('delete')->table($member->trainer));
    }

    public function test_two_open_renewal_confirmations_only_extend_once(): void
    {
        $this->actingAs(User::factory()->create());
        $member = PersonalTrainingMember::factory()->create(['end_date' => '2026-06-30']);
        $renew = TestAction::make('renewOneMonth')->table($member);
        $first = Livewire::test(ListPersonalTrainingMembers::class)->mountAction($renew);
        $second = Livewire::test(ListPersonalTrainingMembers::class)->mountAction($renew);
        $first->callMountedAction();
        $second->callMountedAction();
        $this->assertSame('2026-07-30', $member->fresh()->end_date->toDateString());
    }

    public function test_list_filters_and_trainer_active_member_count(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $active = PersonalTrainingMember::factory()->create(['end_date' => '2026-06-10']);
        $expired = PersonalTrainingMember::factory()->for($active->trainer)->create(['end_date' => '2026-06-09']);
        $inactive = PersonalTrainingMember::factory()->create(['active' => false]);
        $upcoming = PersonalTrainingMember::factory()->for($active->trainer)->create(['start_date' => '2026-06-11', 'end_date' => '2026-06-17']);
        $paid = PersonalTrainingMember::factory()->create(['member_payment_paid' => true, 'trainer_payment_paid' => true]);
        Livewire::test(ListPersonalTrainingMembers::class)
            ->assertTableFilterExists('status', fn (SelectFilter $filter): bool => ($filter->getOptions()['upcoming'] ?? null) === 'Upcoming')
            ->assertCanSeeTableRecords([$active, $expired, $inactive, $paid, $upcoming])
            ->filterTable('trainer', $active->trainer_id)->assertCanSeeTableRecords([$active, $expired, $upcoming])->assertCanNotSeeTableRecords([$inactive, $paid])
            ->resetTableFilters()->filterTable('status', 'active')->assertCanSeeTableRecords([$active, $paid])->assertCanNotSeeTableRecords([$expired, $inactive, $upcoming])
            ->resetTableFilters()->filterTable('status', 'completed')->assertCanSeeTableRecords([$expired])->assertCanNotSeeTableRecords([$active, $inactive, $paid, $upcoming])
            ->resetTableFilters()->filterTable('status', 'cancelled')->assertCanSeeTableRecords([$inactive])->assertCanNotSeeTableRecords([$active, $expired, $paid, $upcoming])
            ->resetTableFilters()->filterTable('status', 'upcoming')->assertCanSeeTableRecords([$upcoming])->assertCanNotSeeTableRecords([$active, $expired, $inactive, $paid])
            ->assertTableColumnStateSet('training_status', 'upcoming', $upcoming)
            ->assertTableColumnExists('training_status', fn (TextColumn $column): bool => $column->isBadge() && $column->getColor($column->getState()) === 'info', $upcoming)
            ->resetTableFilters()->filterTable('trainer_payment_paid', false)->assertCanSeeTableRecords([$active])->assertCanNotSeeTableRecords([$paid]);
        Livewire::test(ListTrainers::class)->assertTableColumnStateSet('personal_training_members_count', 1, $active->trainer);
    }
}
