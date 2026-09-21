<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\EnrollmentStats;
use App\Filament\Widgets\OverdueBalances;
use App\Filament\Widgets\PersonalTrainingStats;
use App\Filament\Widgets\RenewalsDue;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PendingApprovalsDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** @return array<int, array{string}> */
    public static function roles(): array
    {
        return [['admin'], ['staff']];
    }

    #[DataProvider('roles')]
    public function test_dashboard_only_mounts_actionable_table_widgets(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));

        $this->get('/admin')
            ->assertOk()
            ->assertDontSee('pending review')
            ->assertDontSee('Pending approvals')
            ->assertSee('Review overdue balances and upcoming membership renewals.');

        $this->assertSame([
            OverdueBalances::class,
            RenewalsDue::class,
        ], array_values(app(Dashboard::class)->getWidgets()));

        Livewire::test(Dashboard::class)
            ->assertDontSeeLivewire(EnrollmentStats::class)
            ->assertDontSeeLivewire(PersonalTrainingStats::class)
            ->assertSeeLivewire(OverdueBalances::class)
            ->assertSeeLivewire(RenewalsDue::class);
    }

    #[DataProvider('roles')]
    public function test_enrollment_stats_have_no_pending_approval_stat(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));

        $stats = collect(Livewire::test(EnrollmentStats::class)
            ->instance()
            ->getSchema('content')
            ->getComponents()[0]
            ->getChildSchema()
            ->getComponents());

        $this->assertFalse($stats->contains(
            fn (Stat $stat): bool => $stat->getLabel() === 'Pending approvals',
        ));
    }
}
