<?php

namespace Tests\Feature;

use App\Enums\ProfitFrequency as F;
use App\Exceptions\BusinessRuleException;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\User;
use App\Services\Performance\ProfitSchedule;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Only the profit-payment options that mean something for a plan's length are offered, and enforced. */
class ProfitFrequencyAvailabilityTest extends TestCase
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
        $user->investorProfile()->create(['phone' => '08031234567', 'kyc_status' => 'approved', 'address' => 'Abuja', 'date_of_birth' => '1980-01-01']);

        return $user;
    }

    public function test_the_options_follow_the_plan_length(): void
    {
        $this->assertSame([F::AtMaturity], F::availableFor(1));
        $this->assertSame([F::Monthly, F::AtMaturity], F::availableFor(2));
        $this->assertSame([F::Monthly, F::AtMaturity], F::availableFor(3));
        $this->assertSame([F::Monthly, F::Quarterly, F::AtMaturity], F::availableFor(6));
        $this->assertSame([F::Monthly, F::Quarterly, F::HalfYearly, F::AtMaturity], F::availableFor(9));
        $this->assertSame([F::Monthly, F::Quarterly, F::HalfYearly, F::AtMaturity], F::availableFor(12));
        $this->assertSame(F::cases(), F::availableFor(13));
        $this->assertSame(F::cases(), F::availableFor(24));
    }

    public function test_the_plans_page_lists_only_the_options_for_each_plan(): void
    {
        $page = $this->actingAs($this->investor())->get(route('investor.plans'))->assertOk();

        $forms = preg_split('/<input type="hidden" name="investment_plan_id"/', $page->getContent());
        array_shift($forms);
        $this->assertCount(3, $forms);
        $values = fn (string $form) => preg_match_all('/name="profit_frequency" value="([a-z_]+)"/', $form, $m) ? $m[1] : [];

        $this->assertSame(['monthly', 'at_maturity'], $values($forms[0]), '3 months');
        $this->assertSame(['monthly', 'quarterly', 'at_maturity'], $values($forms[1]), '6 months');
        $this->assertSame(['monthly', 'quarterly', 'half_yearly', 'at_maturity'], $values($forms[2]), '12 months');
        $page->assertSee('Options for a 3-month plan');
    }

    public function test_an_unavailable_choice_is_refused_by_the_server_even_if_the_form_is_tampered_with(): void
    {
        $user = $this->investor();
        $three = InvestmentPlan::where('slug', '3-month')->first();

        foreach (['quarterly', 'half_yearly', 'annually'] as $bad) {
            $this->actingAs($user)->post(route('investor.investments.store'), ['investment_plan_id' => $three->id, 'amount' => '500,000', 'profit_frequency' => $bad, 'accept_terms' => '1'])
                ->assertSessionHasErrors('amount');
        }
        $this->assertSame(0, Investment::count());

        $this->actingAs($user)->post(route('investor.investments.store'), ['investment_plan_id' => $three->id, 'amount' => '500,000', 'profit_frequency' => 'at_maturity', 'accept_terms' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(F::AtMaturity, Investment::firstOrFail()->profit_frequency);
    }

    public function test_the_error_names_the_options_that_are_available(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Choose one of: Monthly, One-Off Payment.');

        app(SubscriptionService::class)->subscribe($this->investor(), InvestmentPlan::where('slug', '3-month')->first(), 500_000_00, F::Quarterly, ['ip' => '1.1.1.1']);
    }

    public function test_a_nine_month_plan_pays_half_yearly_at_month_six_and_the_rest_at_maturity(): void
    {
        $plan = InvestmentPlan::create(['name' => '9-Month Plan', 'slug' => '9-month', 'duration_months' => 9, 'minimum_amount' => 500_000_00, 'is_active' => true, 'sort_order' => 5]);
        $i = app(SubscriptionService::class)->subscribe($this->investor(), $plan, 500_000_00, F::HalfYearly, ['ip' => '1.1.1.1']);
        $i->update(['start_date' => '2026-10-20', 'maturity_date' => '2027-07-20']);

        $due = fn (string $m) => ProfitSchedule::isDue($i->fresh(), Carbon::parse("$m-01"));
        $this->assertFalse($due('2027-03'));
        $this->assertTrue($due('2027-04'), 'six months in');
        $this->assertFalse($due('2027-05'));
        $this->assertTrue($due('2027-07'), 'the final run at maturity');
    }
}
