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
    public function test_dashboard_has_no_pending_approval_queue_or_copy(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));

        $this->get('/admin')
            ->assertOk()
            ->assertDontSee('pending review')
            ->assertDontSee('Pending approvals');

        $this->assertSame([
            EnrollmentStats::class,
            PersonalTrainingStats::class,
            OverdueBalances::class,
            RenewalsDue::class,
        ], array_values(app(Dashboard::class)->getWidgets()));
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
