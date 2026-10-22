<?php

namespace Tests\Feature;

use App\Models\InvestmentPlan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('super-admin');
    }

    private function order(): array
    {
        return InvestmentPlan::orderBy('sort_order')->orderBy('id')->pluck('slug')->all();
    }

    private function move(string $slug, string $direction, ?User $by = null)
    {
        return $this->actingAs($by ?? $this->admin())->post(route('admin.plans.move', InvestmentPlan::where('slug', $slug)->first()), ['direction' => $direction]);
    }

    public function test_moving_a_plan_changes_the_order_investors_see(): void
    {
        $this->assertSame(['3-month', '6-month', '12-month'], $this->order());

        $this->move('12-month', 'up')->assertSessionHasNoErrors();
        $this->assertSame(['3-month', '12-month', '6-month'], $this->order());
        $this->move('3-month', 'down');
        $this->assertSame(['12-month', '3-month', '6-month'], $this->order());

        $investor = User::factory()->create()->assignRole('investor');
        $investor->investorProfile()->create(['phone' => '08031234567', 'kyc_status' => 'approved', 'address' => 'Abuja', 'date_of_birth' => '1980-01-01']);
        $this->actingAs($investor)->get(route('investor.plans'))->assertOk()->assertSeeInOrder(['12-Month Plan', '3-Month Plan', '6-Month Plan']);
        $this->assertDatabaseHas('activity_log', ['description' => 'Plan order changed']);
    }

    public function test_the_first_cannot_move_up_nor_the_last_down_and_positions_stay_unique_and_gap_free(): void
    {
        $this->move('3-month', 'up');
        $this->move('12-month', 'down');
        $this->assertSame(['3-month', '6-month', '12-month'], $this->order());

        foreach ([['6-month', 'down'], ['3-month', 'down'], ['12-month', 'up'], ['6-month', 'up']] as [$slug, $dir]) {
            $this->move($slug, $dir);
        }
        $this->assertSame([1, 2, 3], InvestmentPlan::orderBy('sort_order')->pluck('sort_order')->all());
    }

    public function test_a_new_plan_goes_to_the_end_and_editing_never_changes_the_position(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.plans.store'), ['name' => '9-Month Plan', 'duration_months' => 9, 'minimum_amount' => '500000', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('9-month-plan', str_starts_with(InvestmentPlan::orderByDesc('sort_order')->first()->slug, '9-month-plan') ? '9-month-plan' : 'x');
        $this->assertSame(4, InvestmentPlan::max('sort_order'));

        $first = InvestmentPlan::where('slug', '3-month')->first();
        $this->actingAs($admin)->put(route('admin.plans.update', $first), ['name' => 'Renamed', 'duration_months' => 3, 'minimum_amount' => '500000', 'sort_order' => 99, 'is_active' => '1']);
        $this->assertSame(1, $first->fresh()->sort_order);
    }

    public function test_the_page_shows_positions_and_only_the_arrows_that_apply(): void
    {
        $page = $this->actingAs($this->admin())->get(route('admin.plans'))->assertOk();

        $this->assertSame(2, substr_count($page->getContent(), 'name="direction" value="up"'), 'the first plan has no up arrow');
        $this->assertSame(2, substr_count($page->getContent(), 'name="direction" value="down"'), 'the last plan has no down arrow');
    }

    public function test_an_inactive_plan_keeps_its_place_but_is_hidden_from_investors(): void
    {
        InvestmentPlan::where('slug', '6-month')->update(['is_active' => false]);
        $this->actingAs($this->admin())->get(route('admin.plans'))->assertSee('Hidden from investors');

        $this->move('12-month', 'up');
        $this->assertSame(['3-month', '12-month', '6-month'], $this->order());
    }

    public function test_only_plan_managers_can_reorder_and_the_direction_is_validated(): void
    {
        $this->move('12-month', 'up', User::factory()->create()->assignRole('hr'))->assertForbidden();
        $this->move('12-month', 'up', User::factory()->create()->assignRole('investor'))->assertForbidden();
        $this->move('12-month', 'sideways')->assertSessionHasErrors('direction');
        $this->assertSame(['3-month', '6-month', '12-month'], $this->order());
    }
}
