<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Enrollments\Pages\ViewEnrollment;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\OverdueBalances;
use App\Models\Enrollment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_create_a_staff_user(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Front Desk Staff',
                'email' => 'staff@example.com',
                'role' => UserRole::Staff->value,
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = User::query()->where('email', 'staff@example.com')->firstOrFail();

        $this->assertTrue($staff->isStaff());
        $this->assertTrue(Hash::check('secure-password', $staff->password));
    }

    public function test_staff_cannot_access_user_management(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get(UserResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_staff_enrollment_pages_hide_critical_data_and_actions(): void
    {
        $staff = User::factory()->staff()->create();
        $enrollment = Enrollment::factory()->create([
            'full_name' => 'Visible Member',
            'email' => 'private-member@example.com',
            'mobile_number' => '9999988888',
            'address' => 'Private home address',
            'emergency_contact' => '7777766666',
            'payment_mode' => 'Private bank transfer',
            'amount_paid' => 12345,
            'has_balance' => true,
            'remaining_balance' => 6789,
            'balance_due_date' => today()->subDays(5),
        ]);

        $listResponse = $this->actingAs($staff)->get(EnrollmentResource::getUrl('index'));

        $listResponse
            ->assertOk()
            ->assertSee('Visible Member')
            ->assertDontSee('private-member@example.com')
            ->assertDontSee('9999988888')
            ->assertDontSee('Private bank transfer')
            ->assertSee(today()->subDays(5)->format('d M Y'))
            ->assertDontSee('Download CSV')
            ->assertDontSee('Send confirmation again')
            ->assertSee('Mark balance as paid');

        $this->actingAs($staff)
            ->get(EnrollmentResource::getUrl('view', ['record' => $enrollment]))
            ->assertOk()
            ->assertSee('Visible Member')
            ->assertDontSee('private-member@example.com')
            ->assertDontSee('9999988888')
            ->assertDontSee('Private home address')
            ->assertDontSee('7777766666')
            ->assertDontSee('Private bank transfer')
            ->assertDontSee('Amount paid')
            ->assertDontSee('12,345.00')
            ->assertDontSee('Remaining balance')
            ->assertDontSee('6,789.00')
            ->assertSee(today()->subDays(5)->format('d M Y'))
            ->assertDontSee('Download confirmation')
            ->assertDontSee('Send confirmation again')
            ->assertSee('Mark balance as paid');

        Livewire::actingAs($staff)
            ->test(ViewEnrollment::class, ['record' => $enrollment->getRouteKey()])
            ->assertActionHidden('downloadConfirmation')
            ->assertActionHidden('sendConfirmationAgain')
            ->assertActionVisible('markBalancePaid')
            ->callAction('markBalancePaid');

        $this->assertFalse($enrollment->refresh()->has_balance);
    }

    public function test_staff_dashboard_hides_financial_data(): void
    {
        $staff = User::factory()->staff()->create();
        $enrollment = Enrollment::factory()->create([
            'full_name' => 'Member With Overdue Balance',
            'mobile_number' => '9888877777',
            'has_balance' => true,
            'remaining_balance' => 54321,
            'balance_due_date' => today()->subDays(4),
        ]);

        $this->actingAs($staff)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Membership overview')
            ->assertSee('Monitor pending review, personal training, memberships, and renewals.')
            ->assertDontSee('Amount recorded as paid')
            ->assertDontSee('Outstanding balance');

        Livewire::actingAs($staff)
            ->test(OverdueBalances::class)
            ->assertCanSeeTableRecords([$enrollment])
            ->assertTableColumnVisible('full_name')
            ->assertTableColumnHidden('amount_paid')
            ->assertTableColumnHidden('remaining_balance')
            ->assertTableColumnVisible('balance_due_date')
            ->assertTableColumnVisible('days_overdue')
            ->assertTableColumnHidden('mobile_number')
            ->assertDontSee('9888877777');
    }
}
