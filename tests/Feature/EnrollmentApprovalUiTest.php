<?php

namespace Tests\Feature;

use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Resources\Enrollments\Pages\ViewEnrollment;
use App\Models\Enrollment;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnrollmentApprovalUiTest extends TestCase
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
    public function test_admin_pages_have_no_enrollment_approval_action(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $pending = Enrollment::factory()->create(['approval_status' => 'pending']);

        Livewire::actingAs($user)
            ->test(ListEnrollments::class)
            ->assertTableActionDoesNotExist('approveEnrollment');

        Livewire::actingAs($user)
            ->test(ViewEnrollment::class, ['record' => $pending->getRouteKey()])
            ->assertActionDoesNotExist('approveEnrollment');

        $this->assertFalse(Gate::forUser($user)->allows('approve', $pending));
    }

    public function test_list_keeps_status_visibility_and_filters_all_final_decisions(): void
    {
        $pending = Enrollment::factory()->create(['approval_status' => 'pending']);
        $approved = Enrollment::factory()->create(['approval_status' => 'approved']);
        $rejected = Enrollment::factory()->create(['approval_status' => 'rejected']);

        $page = Livewire::actingAs(User::factory()->staff()->create())
            ->test(ListEnrollments::class)
            ->assertCanSeeTableRecords([$pending, $approved, $rejected])
            ->assertTableColumnExists('approval_status', fn (TextColumn $column): bool => $column->isBadge())
            ->assertSee('Pending')
            ->assertSee('Approved')
            ->assertSee('Rejected');

        foreach (['pending' => $pending, 'approved' => $approved, 'rejected' => $rejected] as $status => $record) {
            $page->filterTable('approval_status', $status)
                ->assertCanSeeTableRecords([$record]);
        }
    }
}
