<?php

namespace Tests\Feature;

use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Models\Enrollment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class EnrollmentFiltersTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_filter_enrollments_by_membership_and_balance_status(): void
    {
        $this->travelTo(Carbon::parse('2026-08-19 10:00:00'));

        $user = User::factory()->create();
        $active = Enrollment::factory()->create([
            'membership_start_date' => today()->subMonth(),
            'membership_end_date' => today()->addMonth(),
            'has_balance' => false,
            'remaining_balance' => null,
            'balance_due_date' => null,
        ]);
        $overdue = Enrollment::factory()->create([
            'membership_start_date' => today()->subMonth(),
            'membership_end_date' => today()->addMonth(),
            'has_balance' => true,
            'remaining_balance' => '500.00',
            'balance_due_date' => today()->subDay(),
        ]);
        $expired = Enrollment::factory()->create([
            'membership_start_date' => today()->subMonths(2),
            'membership_end_date' => today()->subDay(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($user)
            ->test(ListEnrollments::class)
            ->assertTableFilterExists('membership_status')
            ->assertTableFilterExists('package_duration')
            ->assertTableFilterExists('balance_status')
            ->assertTableFilterExists('payment_category')
            ->assertTableFilterExists('freezing_enabled')
            ->assertTableFilterExists('membership_period')
            ->assertTableFilterExists('submitted_period')
            ->filterTable('membership_status', 'expired')
            ->assertCanSeeTableRecords([$expired])
            ->assertCanNotSeeTableRecords([$active, $overdue])
            ->resetTableFilters()
            ->filterTable('balance_status', 'overdue')
            ->assertCanSeeTableRecords([$overdue])
            ->assertCanNotSeeTableRecords([$active, $expired]);
    }
}
