<?php

namespace Tests\Feature;

use App\Enums\ProfitFrequency;
use App\Exceptions\BusinessRuleException;
use App\Models\InvestmentPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A plan can have a maximum for one subscription; empty means no maximum. */
class PlanMaximumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function investor(): User
    {
        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved', 'nin' => (string) random_int(10_000_000_000, 99_999_999_999)]);

        return $user;
    }

    private function plan(): InvestmentPlan
    {
        return InvestmentPlan::where('slug', '6-month')->first();
    }

    private function save(InvestmentPlan $plan, array $over = [])
    {
        $director = User::factory()->create()->assignRole('super-admin');

        return $this->actingAs($director)->put(route('admin.plans.update', $plan), $over + ['name' => $plan->name, 'duration_months' => $plan->duration_months, 'minimum_amount' => '500000', 'is_active' => '1']);
    }

    public function test_existing_plans_have_no_maximum_and_take_any_amount(): void
    {
        $this->assertNull($this->plan()->maximum_amount);

        $i = app(SubscriptionService::class)->subscribe($this->investor(), $this->plan(), 5_000_000_000_00, ProfitFrequency::Monthly);

        $this->assertSame(5_000_000_000_00, $i->amount);
    }

    public function test_an_amount_above_the_maximum_is_refused_and_one_at_it_is_accepted(): void
    {
        $this->plan()->update(['maximum_amount' => 10_000_000_00]);
        $service = app(SubscriptionService::class);

        try {
            $service->subscribe($this->investor(), $this->plan()->fresh(), 10_000_001_00, ProfitFrequency::Monthly);
            $this->fail('over the maximum');
        } catch (BusinessRuleException $e) {
            $this->assertSame('The maximum investment for this plan is ₦10,000,000.00. For a larger amount, please contact us.', $e->getMessage());
        }
        $this->assertSame(0, \App\Models\Investment::count());

        $this->assertSame(10_000_000_00, $service->subscribe($this->investor(), $this->plan()->fresh(), 10_000_000_00, ProfitFrequency::Monthly)->amount);
    }

    public function test_the_investor_form_refuses_it_with_a_clear_message_and_the_page_shows_the_maximum(): void
    {
        $this->plan()->update(['maximum_amount' => 10_000_000_00]);
        $user = $this->investor();

        $this->actingAs($user)->get(route('investor.plans'))->assertOk()->assertSee('Maximum')->assertSee('₦10,000,000.00');
        $this->actingAs($user)->post(route('investor.investments.store'), ['investment_plan_id' => $this->plan()->id, 'amount' => '25,000,000', 'profit_frequency' => 'monthly', 'accept_terms' => '1'])
            ->assertSessionHasErrors('amount');
        $this->assertSame(0, \App\Models\Investment::count());
    }

    public function test_plans_without_a_maximum_show_none(): void
    {
        $this->actingAs($this->investor())->get(route('investor.plans'))->assertOk()->assertDontSee('Maximum');
    }

    public function test_the_super_admin_sets_changes_and_clears_a_maximum(): void
    {
        $plan = $this->plan();

        $this->save($plan, ['maximum_amount' => '10,000,000'])->assertSessionHasNoErrors();
        $this->assertSame(10_000_000_00, $plan->fresh()->maximum_amount);
        $this->assertDatabaseHas('activity_log', ['description' => 'Plan saved']);

        $this->save($plan, ['maximum_amount' => '20000000'])->assertSessionHasNoErrors();
        $this->assertSame(20_000_000_00, $plan->fresh()->maximum_amount);

        $this->save($plan, ['maximum_amount' => ''])->assertSessionHasNoErrors();
        $this->assertNull($plan->fresh()->maximum_amount, 'empty means no maximum');
    }

    public function test_a_maximum_below_the_minimum_or_not_a_number_is_refused(): void
    {
        $plan = $this->plan();

        $this->save($plan, ['maximum_amount' => '400000'])->assertSessionHasErrors(['maximum_amount' => 'The maximum cannot be below the minimum.']);
        $this->save($plan, ['maximum_amount' => 'lots'])->assertSessionHasErrors('maximum_amount');
        $this->assertNull($plan->fresh()->maximum_amount);

        $this->save($plan, ['maximum_amount' => '500000'])->assertSessionHasNoErrors(); // equal to the minimum is allowed
        $this->assertSame(500_000_00, $plan->fresh()->maximum_amount);
    }

    public function test_a_new_plan_can_be_created_with_a_maximum_and_the_admin_page_has_the_field(): void
    {
        $director = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($director)->post(route('admin.plans.store'), ['name' => 'Capped Plan', 'duration_months' => 9, 'minimum_amount' => '500000', 'maximum_amount' => '5000000', 'is_active' => '1'])->assertSessionHasNoErrors();

        $this->assertSame(5_000_000_00, InvestmentPlan::where('name', 'Capped Plan')->first()->maximum_amount);
        $this->actingAs($director)->get(route('admin.plans'))->assertOk()->assertSee('Maximum (₦, optional)')->assertSee('No maximum', false);
    }

    public function test_lowering_a_maximum_never_touches_existing_investments(): void
    {
        $i = app(SubscriptionService::class)->subscribe($this->investor(), $this->plan(), 20_000_000_00, ProfitFrequency::Monthly);

        $this->plan()->update(['maximum_amount' => 10_000_000_00]);

        $this->assertSame(20_000_000_00, $i->fresh()->amount);
    }
}
