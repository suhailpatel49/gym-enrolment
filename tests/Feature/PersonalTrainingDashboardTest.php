<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\PersonalTrainingStats;
use App\Models\PersonalTrainingMember;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonalTrainingDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function roles(): array
    {
        return [[UserRole::Admin], [UserRole::Staff]];
    }

    #[DataProvider('roles')]
    public function test_three_stats_count_current_members_and_include_seven_day_boundary(UserRole $role): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->travelTo(Carbon::parse('2026-06-10'));
        PersonalTrainingMember::factory()->create(['end_date' => '2026-06-10']);
        PersonalTrainingMember::factory()->create(['end_date' => '2026-06-17']);
        PersonalTrainingMember::factory()->create(['end_date' => '2026-06-18', 'trainer_payment_paid' => true]);
        PersonalTrainingMember::factory()->create(['end_date' => '2026-06-09']);
        PersonalTrainingMember::factory()->create(['end_date' => '2026-06-12', 'active' => false]);

        PersonalTrainingMember::factory()->create(['start_date' => '2026-06-11', 'end_date' => '2026-06-17']);

        $widget = Livewire::test(PersonalTrainingStats::class);
        $stats = $widget->instance()->getSchema('content')->getComponents()[0]->getChildSchema()->getComponents();
        $this->assertCount(3, $stats);
        $this->assertSame([3, 2, 2], array_map(fn ($stat): int => (int) $stat->getValue(), $stats));
        $widget->assertSee('Active PT members')->assertSee('Trainer payments pending')->assertSee('Subscriptions expiring within 7 days');
        $this->assertContains(PersonalTrainingStats::class, app(Dashboard::class)->getWidgets());
        $this->get('/admin')->assertOk()->assertSee('Membership overview');
    }
}
