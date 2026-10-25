<?php

namespace Tests\Feature;

use App\Enums\InvestmentStatus;
use App\Enums\ProfitFrequency;
use App\Models\DistributionLine;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\LedgerAccount;
use App\Models\PerformanceEntry;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Payments\PaymentService;
use App\Services\Performance\DistributionService;
use App\Services\Performance\PerformanceService;
use App\Services\Performance\ProfitSchedule;
use App\Services\Performance\ProfitSplit;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ProfitFrequencyTest extends TestCase
{
    use RefreshDatabase;

    private DistributionService $distributions;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->distributions = app(DistributionService::class);
        $this->director = User::factory()->create()->assignRole('super-admin');
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    }

    private function policy(ProfitFrequency $frequency, string $name = 'Investor', int $amount = 1_000_000_00): Investment
    {
        $user = User::factory()->create(['name' => $name])->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved', 'bank_name' => 'Test Bank', 'account_number' => '0123456789', 'account_name' => $name]);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '12-month')->first(), $amount, $frequency);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh();
    }

    /** Record a cycle result for one policy on a given day. */
    private function record(Investment $i, int $net, string $day, string $cycle): void
    {
        $this->travelTo(Carbon::parse($day.' 10:00'));
        app(PerformanceService::class)->recordCycleResult($cycle, [$i->id => $i->amount], $net);
    }

    private function runMonth(string $month): \App\Models\DistributionRun
    {
        $run = $this->distributions->calculate(Carbon::parse($month.'-01'), null, notify: false);

        return $this->distributions->approve($run, $this->director);
    }

    // ---- choosing it ----

    public function test_the_frequency_is_stored_when_subscribing_and_defaults_to_monthly(): void
    {
        $this->assertSame(ProfitFrequency::Monthly, $this->policy(ProfitFrequency::Monthly, 'A')->profit_frequency);
        $q = $this->policy(ProfitFrequency::Quarterly, 'B');
        $this->assertSame(ProfitFrequency::Quarterly, $q->profit_frequency);
        $this->assertSame(ProfitFrequency::AtMaturity, $this->policy(ProfitFrequency::AtMaturity, 'C')->profit_frequency);

        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);
        $default = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::first(), 1_000_000_00);
        $this->assertSame(ProfitFrequency::Monthly, $default->profit_frequency);

        $log = Activity::where('description', 'Investment subscription created')->where('subject_id', $q->id)->firstOrFail();
        $this->assertSame('quarterly', $log->properties['profit_frequency']);
    }

    public function test_investors_choose_it_on_the_plans_page_and_bad_values_are_refused(): void
    {
        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved', 'nin' => '12345678901']);
        $plan = InvestmentPlan::first();

        $this->actingAs($user)->get(route('investor.plans'))->assertOk()
            ->assertSee('How do you want your profit paid?')->assertSee('Quarterly')->assertSee('One-Off Payment')->assertSee('fixed once you subscribe');

        $this->actingAs($user)->post(route('investor.investments.store'), ['investment_plan_id' => $plan->id, 'amount' => '500,000', 'profit_frequency' => 'weekly', 'accept_terms' => '1'])->assertSessionHasErrors('profit_frequency');
        $this->assertSame(0, Investment::count());

        $this->actingAs($user)->post(route('investor.investments.store'), ['investment_plan_id' => $plan->id, 'amount' => '500,000', 'profit_frequency' => 'at_maturity', 'accept_terms' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(ProfitFrequency::AtMaturity, Investment::firstOrFail()->profit_frequency);

        $this->actingAs($user)->post(route('investor.investments.store'), ['investment_plan_id' => $plan->id, 'amount' => '500,000', 'accept_terms' => '1'])->assertSessionHasNoErrors(); // omitted = monthly
        $this->assertSame(ProfitFrequency::Monthly, Investment::orderByDesc('id')->first()->profit_frequency);
    }

    // ---- when a policy is due ----

    public function test_which_months_each_frequency_is_due(): void
    {
        // Started 20 Oct 2026, 12 months: matures 20 Oct 2027.
        $m = $this->policy(ProfitFrequency::Monthly, 'M');
        $q = $this->policy(ProfitFrequency::Quarterly, 'Q');
        $o = $this->policy(ProfitFrequency::AtMaturity, 'O');
        $due = fn (Investment $i, string $month) => ProfitSchedule::isDue($i, Carbon::parse($month.'-01'));

        foreach (['2026-10', '2026-11', '2027-03', '2027-10', '2027-12'] as $month) {
            $this->assertTrue($due($m, $month), "monthly $month");
        }
        foreach (['2026-10', '2026-11', '2026-12', '2027-02', '2027-03', '2027-05'] as $month) {
            $this->assertFalse($due($q, $month), "quarterly $month");
        }
        foreach (['2027-01', '2027-04', '2027-07', '2027-10', '2027-11'] as $month) { // every third month, then the final run and after
            $this->assertTrue($due($q, $month), "quarterly $month");
        }
        foreach (['2026-10', '2027-01', '2027-04', '2027-09'] as $month) {
            $this->assertFalse($due($o, $month), "one-off $month");
        }
        foreach (['2027-10', '2027-11'] as $month) {
            $this->assertTrue($due($o, $month), "one-off $month");
        }
    }

    public function test_next_payment_date_per_policy(): void
    {
        $m = $this->policy(ProfitFrequency::Monthly, 'M');
        $q = $this->policy(ProfitFrequency::Quarterly, 'Q');
        $o = $this->policy(ProfitFrequency::AtMaturity, 'O');
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));

        $this->assertSame('2026-10-31', ProfitSchedule::nextRun($m)['pay_by']->toDateString());
        $this->assertSame('2027-01-31', ProfitSchedule::nextRun($q)['pay_by']->toDateString());
        $this->assertSame('2027-01-27', ProfitSchedule::nextRun($q)['cutoff']->toDateString());
        $this->assertSame('2027-10-31', ProfitSchedule::nextRun($o)['pay_by']->toDateString());

        $o->update(['status' => InvestmentStatus::PaidOut]);
        $this->assertNull(ProfitSchedule::nextRun($o->fresh()));
    }

    // ---- the monthly run honours it ----

    public function test_a_run_only_includes_policies_that_are_due_and_leaves_the_rest_waiting(): void
    {
        $m = $this->policy(ProfitFrequency::Monthly, 'M');
        $q = $this->policy(ProfitFrequency::Quarterly, 'Q');
        $o = $this->policy(ProfitFrequency::AtMaturity, 'O');
        foreach ([$m, $q, $o] as $i) {
            $this->record($i, 100_000_00, '2026-10-21', 'C-OCT');
        }

        $run = $this->runMonth('2026-10');

        $this->assertSame([$m->id], $run->lines()->pluck('investment_id')->all());
        $this->assertSame(2, $run->policies_deferred);
        $this->assertSame(2, PerformanceEntry::whereNull('distribution_run_id')->count(), 'the other two are still waiting');
        $this->assertSame('monthly', $run->lines()->first()->frequency);
        // Only the monthly policy's share was booked as owed.
        $this->assertSame($run->lines()->first()->investor_share, app(LedgerService::class)->balance(LedgerAccount::INVESTOR_PROFIT_PAYABLE));
    }

    public function test_quarterly_pays_every_third_month_netting_the_whole_quarter(): void
    {
        $q = $this->policy(ProfitFrequency::Quarterly, 'Q');
        $this->record($q, 100_000_00, '2026-10-21', 'C1');
        $this->record($q, 50_000_00, '2026-11-21', 'C2');
        $this->record($q, 200_000_00, '2026-12-21', 'C3');
        $this->record($q, 50_000_00, '2027-01-21', 'C4');

        foreach (['2026-10', '2026-11', '2026-12'] as $month) {
            $this->assertSame(0, $this->runMonth($month)->lines()->count(), "$month has nothing due");
        }
        $this->assertSame(4, PerformanceEntry::whereNull('distribution_run_id')->count());

        $jan = $this->runMonth('2027-01');

        $line = $jan->lines()->firstOrFail();
        $this->assertSame(400_000_00, $line->net_profit);
        $this->assertSame((new ProfitSplit)->split(400_000_00)['investor'], $line->investor_share);
        $this->assertSame('quarterly', $line->frequency);
        $this->assertSame('2026-10-21', $line->covers_from->toDateString());
        $this->assertSame('2027-01-21', $line->covers_to->toDateString());
        $this->assertSame('21 Oct - 21 Jan 2027', $line->coverage());
        $this->assertSame(0, PerformanceEntry::whereNull('distribution_run_id')->count());
    }

    public function test_a_loss_inside_the_period_is_netted_for_quarterly_but_not_for_monthly(): void
    {
        $monthly = $this->policy(ProfitFrequency::Monthly, 'M');
        $quarterly = $this->policy(ProfitFrequency::Quarterly, 'Q');
        foreach ([$monthly, $quarterly] as $i) {
            $this->record($i, 300_000_00, '2026-10-21', 'C1');
            $this->record($i, -100_000_00, '2026-11-21', 'C2');
            $this->record($i, 0, '2026-12-21', 'C3');
            $this->record($i, 0, '2027-01-21', 'C4');
        }

        $monthlyPaid = 0;
        foreach (['2026-10', '2026-11', '2026-12'] as $month) {
            $monthlyPaid += (int) $this->runMonth($month)->lines()->where('investment_id', $monthly->id)->sum('investor_share');
        }
        $jan = $this->runMonth('2027-01');

        $this->assertSame((new ProfitSplit)->split(300_000_00)['investor'], $monthlyPaid, 'monthly: the loss month simply pays nothing');
        $line = $jan->lines()->where('investment_id', $quarterly->id)->firstOrFail();
        $this->assertSame(200_000_00, $line->net_profit, 'quarterly: the loss is netted against the profit before the split');
        $this->assertSame((new ProfitSplit)->split(200_000_00)['investor'], $line->investor_share);
    }

    public function test_one_off_pays_everything_in_the_maturity_run(): void
    {
        $o = $this->policy(ProfitFrequency::AtMaturity, 'O');
        $o->update(['maturity_date' => '2027-01-31']); // shorten the term for the test
        $this->record($o, 100_000_00, '2026-10-21', 'C1');
        $this->record($o, 100_000_00, '2026-12-21', 'C2');
        $this->record($o, 100_000_00, '2027-01-21', 'C3');

        foreach (['2026-10', '2026-11', '2026-12'] as $month) {
            $this->assertSame(0, $this->runMonth($month)->lines()->count());
        }
        $final = $this->runMonth('2027-01');

        $this->assertSame(300_000_00, $final->lines()->firstOrFail()->net_profit);
        $this->assertSame('at_maturity', $final->lines()->first()->frequency);
        $this->assertSame(0, $final->policies_deferred);
    }

    public function test_results_recorded_after_maturity_are_still_swept_by_the_next_run(): void
    {
        $o = $this->policy(ProfitFrequency::AtMaturity, 'O');
        $o->update(['maturity_date' => '2026-10-25']);
        $this->record($o, 100_000_00, '2026-10-21', 'C1');
        $this->record($o, 40_000_00, '2026-10-29', 'C2'); // after the 27th cut-off

        $oct = $this->runMonth('2026-10');
        $this->assertSame(100_000_00, $oct->lines()->firstOrFail()->net_profit);

        $nov = $this->runMonth('2026-11');
        $this->assertSame(40_000_00, $nov->lines()->firstOrFail()->net_profit, 'a matured policy is always due, so nothing is stranded');
    }

    public function test_recalculating_a_draft_run_keeps_deferred_policies_waiting(): void
    {
        $m = $this->policy(ProfitFrequency::Monthly, 'M');
        $q = $this->policy(ProfitFrequency::Quarterly, 'Q');
        $this->record($m, 10_000_00, '2026-10-21', 'C1');
        $this->record($q, 10_000_00, '2026-10-21', 'C1');

        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), null, notify: false);
        $again = $this->distributions->calculate(Carbon::parse('2026-10-01'), null, notify: false);

        $this->assertSame($run->id, $again->id);
        $this->assertSame(1, $again->lines()->count());
        $this->assertSame(1, $again->policies_deferred);
        $this->assertSame(1, PerformanceEntry::whereNull('distribution_run_id')->count());
    }

    // ---- what people see ----

    public function test_screens_and_exports_show_the_frequency(): void
    {
        $m = $this->policy(ProfitFrequency::Monthly, 'Mo Investor');
        $q = $this->policy(ProfitFrequency::Quarterly, 'Quarterly Investor');
        $this->record($m, 100_000_00, '2026-10-21', 'C1');
        $this->record($q, 100_000_00, '2026-10-21', 'C1');
        $this->record($q, 100_000_00, '2027-01-21', 'C2');
        $this->runMonth('2026-10');
        $jan = $this->runMonth('2027-01');
        $accounts = User::factory()->create()->assignRole('accounts');

        $this->actingAs($q->user)->get(route('investor.dashboard'))->assertOk()->assertSee('Profit paid:')->assertSee('Quarterly')->assertSee('next by');
        $this->actingAs($q->user)->get(route('investor.investments.show', $q))->assertOk()
            ->assertSee('Profit payment:')->assertSee('every 3 months')->assertSee('Profit payments')->assertSee('profit for 21 Oct - 21 Jan 2027');

        $this->actingAs($accounts)->get(route('admin.distributions.show', $jan))->assertOk()->assertSee('Quarterly')->assertSee('covers 21 Oct - 21 Jan 2027');
        $csv = $this->actingAs($accounts)->get(route('admin.distributions.export', $jan))->streamedContent();
        $this->assertStringContainsString('Frequency,Covers', $csv);
        $this->assertStringContainsString('Quarterly', $csv);

        $report = $this->actingAs($accounts)->get(route('admin.reports.investors', ['format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('Profit frequency', $report);
        $this->assertStringContainsString('Quarterly', $report);
        $this->actingAs($accounts)->get(route('admin.reports.investors'))->assertOk()->assertSee('Quarterly');

        // A month where a quarterly policy has results but is not due says so.
        $this->record($q, 10_000_00, '2027-02-21', 'C3');
        $feb = $this->distributions->calculate(Carbon::parse('2027-02-01'), null, notify: false);
        $this->actingAs($accounts)->get(route('admin.distributions.show', $feb))->assertOk()->assertSee('not due this month');
    }
}
