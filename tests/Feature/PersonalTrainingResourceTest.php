<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\PersonalTrainingMembers\Pages\CreatePersonalTrainingMember;
use App\Filament\Resources\PersonalTrainingMembers\Pages\EditPersonalTrainingMember;
use App\Filament\Resources\PersonalTrainingMembers\Pages\ListPersonalTrainingMembers;
use App\Filament\Resources\PersonalTrainingMembers\Pages\ViewPersonalTrainingMember;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
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

    public function test_manual_status_form_filter_table_and_infolist_use_the_exact_canonical_values(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $trainer = Trainer::factory()->create();
        $options = [
            'pending' => 'Pending',
            'active' => 'Active',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        Livewire::test(CreatePersonalTrainingMember::class)
            ->assertFormFieldExists('training_status', fn (Select $field): bool => $field->getLabel() === 'Training status' && $field->getOptions() === $options)
            ->fillForm([
                'client_name' => 'Manual Status Client',
                'phone' => '987654321',
                'trainer_id' => $trainer->id,
                'payment_mode' => 'Cash',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
                'total_client_amount' => '2500.00',
                'gym_amount' => '0.00',
                'training_status' => 'completed',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $completed = PersonalTrainingMember::query()->sole();
        $pending = PersonalTrainingMember::factory()->create(['training_status' => 'pending']);
        $active = PersonalTrainingMember::factory()->create(['training_status' => 'active']);
        $cancelled = PersonalTrainingMember::factory()->create(['training_status' => 'cancelled']);

        $this->assertSame('completed', $completed->training_status);

        $list = Livewire::test(ListPersonalTrainingMembers::class)
            ->assertTableFilterExists('status', fn (SelectFilter $filter): bool => $filter->getOptions() === $options);

        foreach ([$pending, $active, $completed, $cancelled] as $record) {
            $list->resetTableFilters()
                ->filterTable('status', $record->training_status)
                ->assertCanSeeTableRecords([$record])
                ->assertCanNotSeeTableRecords(array_values(array_filter(
                    [$pending, $active, $completed, $cancelled],
                    fn (PersonalTrainingMember $other): bool => ! $other->is($record),
                )));
        }

        $list->resetTableFilters();

        foreach ([
            [$pending, 'warning'],
            [$active, 'success'],
            [$completed, 'gray'],
            [$cancelled, 'danger'],
        ] as [$record, $color]) {
            $list->assertTableColumnStateSet('training_status', $record->training_status, $record)
                ->assertTableColumnExists(
                    'training_status',
                    fn (TextColumn $column): bool => $column->isBadge() && $column->getColor($column->getState()) === $color,
                    $record,
                );
        }

        Livewire::test(ViewPersonalTrainingMember::class, ['record' => $completed->id])
            ->assertSchemaComponentStateSet('training_status', 'completed', 'infolist')
            ->assertSchemaComponentExists(
                'training_status',
                'infolist',
                fn (TextEntry $entry): bool => $entry->getLabel() === 'Training status'
                    && $entry->isBadge()
                    && $entry->getColor($entry->getState()) === 'gray',
            );
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

    public function test_number_of_sessions_is_optional_bounded_integer_and_visible_in_list_and_detail(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $trainer = Trainer::factory()->create();
        $form = [
            'client_name' => 'Session Client',
            'phone' => '987654321',
            'trainer_id' => $trainer->id,
            'payment_mode' => 'Cash',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'total_client_amount' => '2500.00',
            'gym_amount' => '0.00',
            'training_status' => 'active',
        ];

        Livewire::test(CreatePersonalTrainingMember::class)
            ->assertFormFieldExists('number_of_sessions', fn (TextInput $field): bool => $field->getLabel() === 'Number of sessions')
            ->fillForm([...$form, 'number_of_sessions' => 0])
            ->call('create')
            ->assertHasFormErrors(['number_of_sessions']);
        Livewire::test(CreatePersonalTrainingMember::class)
            ->fillForm([...$form, 'number_of_sessions' => 1.5])
            ->call('create')
            ->assertHasFormErrors(['number_of_sessions']);
        Livewire::test(CreatePersonalTrainingMember::class)
            ->fillForm([...$form, 'number_of_sessions' => 10_001])
            ->call('create')
            ->assertHasFormErrors(['number_of_sessions']);
        Livewire::test(CreatePersonalTrainingMember::class)
            ->fillForm([...$form, 'number_of_sessions' => 37])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = PersonalTrainingMember::query()->sole();
        $this->assertSame(37, $member->number_of_sessions);

        Livewire::test(EditPersonalTrainingMember::class, ['record' => $member->id])
            ->fillForm(['number_of_sessions' => null, 'trainer_payment_paid' => true])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull($member->fresh()->number_of_sessions);

        Livewire::test(ListPersonalTrainingMembers::class)
            ->assertTableColumnStateSet('number_of_sessions', null, $member)
            ->assertTableColumnExists(
                'number_of_sessions',
                fn (TextColumn $column): bool => $column->getLabel() === 'Number of sessions' && $column->getPlaceholder() === '—',
                $member,
            );
        Livewire::test(ViewPersonalTrainingMember::class, ['record' => $member->id])
            ->assertSchemaComponentStateSet('number_of_sessions', null, 'infolist')
            ->assertSchemaComponentExists(
                'number_of_sessions',
                'infolist',
                fn (TextEntry $entry): bool => $entry->getLabel() === 'Number of sessions' && $entry->getPlaceholder() === '—',
            );
    }

    public function test_remarks_is_optional_limited_persisted_and_visible_in_details(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $trainer = Trainer::factory()->create();
        $member = PersonalTrainingMember::factory()->for($trainer)->create([
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'number_of_sessions' => 12,
            'remark' => null,
        ]);

        Livewire::test(EditPersonalTrainingMember::class, ['record' => $member->id])
            ->assertFormFieldExists('remark', fn (Textarea $field): bool => $field->getLabel() === 'Remarks'
                && ! $field->isRequired()
                && $field->getMaxLength() === 2000)
            ->fillForm(['remark' => str_repeat('x', 2001), 'trainer_payment_paid' => true])
            ->call('save')
            ->assertHasFormErrors(['remark' => 'max']);

        Livewire::test(EditPersonalTrainingMember::class, ['record' => $member->id])
            ->fillForm(['remark' => 'Focus on mobility', 'trainer_payment_paid' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Focus on mobility', $member->fresh()->remark);

        Livewire::test(ViewPersonalTrainingMember::class, ['record' => $member->id])
            ->assertSchemaComponentStateSet('remark', 'Focus on mobility', 'infolist')
            ->assertSchemaComponentExists(
                'remark',
                'infolist',
                fn (TextEntry $entry): bool => $entry->getLabel() === 'Remarks' && $entry->getPlaceholder() === '—',
            );

        $this->get(TrainerResource::getUrl('month', ['record' => $trainer, 'month' => '2026-06']))
            ->assertOk()
            ->assertSee('Number of sessions')
            ->assertSee('12')
            ->assertSee('Remarks')
            ->assertSee('Focus on mobility');
    }

    #[DataProvider('roles')]
    public function test_row_actions_renew_and_cancel_manual_status_without_changing_preserved_data(UserRole $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $member = PersonalTrainingMember::factory()->create([
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
            'training_status' => 'completed',
            'number_of_sessions' => 12,
            'member_payment_paid' => true, 'trainer_payment_paid' => true,
            'remark' => 'Keep this arrangement',
        ]);
        $renew = TestAction::make('renewOneMonth')->table($member);
        $page = Livewire::test(ListPersonalTrainingMembers::class)->mountAction($renew);
        $page->assertMountedActionModalDontSee('completed');
        $this->assertSame('2026-06-30', $member->fresh()->end_date->toDateString());
        $page->callMountedAction();
        $this->assertSame('2026-07-30', $member->fresh()->end_date->toDateString());
        $this->assertSame('active', $member->fresh()->training_status);
        $this->assertFalse($member->fresh()->member_payment_paid);
        $this->assertFalse($member->fresh()->trainer_payment_paid);
        $beforeCancellation = $member->fresh()->only(['start_date', 'end_date', 'number_of_sessions', 'total_client_amount', 'gym_amount', 'trainer_amount', 'trainer_payment_paid', 'remark']);
        $deactivate = TestAction::make('deactivate')->table($member);
        $duplicateDeactivation = Livewire::test(ListPersonalTrainingMembers::class)->mountAction($deactivate);
        $page->mountAction($deactivate);
        $this->assertSame('active', $member->fresh()->training_status);
        $page->callMountedAction();
        $this->assertSame('cancelled', $member->fresh()->training_status);
        $duplicateDeactivation->callMountedAction();
        $this->assertSame('cancelled', $member->fresh()->training_status);
        $this->assertEquals($beforeCancellation, $member->fresh()->only(array_keys($beforeCancellation)));
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

    public function test_trainer_filter_and_active_member_count_use_manual_status(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $active = PersonalTrainingMember::factory()->create([
            'training_status' => 'active',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ]);
        $completed = PersonalTrainingMember::factory()->for($active->trainer)->create(['training_status' => 'completed']);
        $pending = PersonalTrainingMember::factory()->for($active->trainer)->create(['training_status' => 'pending']);
        $cancelled = PersonalTrainingMember::factory()->create(['training_status' => 'cancelled']);
        $paid = PersonalTrainingMember::factory()->create(['member_payment_paid' => true, 'trainer_payment_paid' => true]);
        Livewire::test(ListPersonalTrainingMembers::class)
            ->assertCanSeeTableRecords([$active, $completed, $pending, $cancelled, $paid])
            ->filterTable('trainer', $active->trainer_id)
            ->assertCanSeeTableRecords([$active, $completed, $pending])
            ->assertCanNotSeeTableRecords([$cancelled, $paid]);
        Livewire::test(ListTrainers::class)->assertTableColumnStateSet('personal_training_members_count', 1, $active->trainer);
    }
}
