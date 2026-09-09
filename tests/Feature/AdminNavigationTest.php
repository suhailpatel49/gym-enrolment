<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_sidebar_separates_enrolment_and_personal_training_resources(): void
    {
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $groups = Filament::getNavigation();

        $this->assertSame([null, 'Enrolment', 'PT'], array_values(array_map(
            fn (NavigationGroup $group): ?string => $group->getLabel(),
            $groups,
        )));
        $this->assertSame([
            ['Membership overview', 'Users'],
            ['Enrollments'],
            ['Personal Training Members', 'Trainers'],
        ], array_values(array_map(
            fn (NavigationGroup $group): array => collect($group->getItems())
                ->map(fn (NavigationItem $item): string => $item->getLabel())
                ->all(),
            $groups,
        )));
    }
}
