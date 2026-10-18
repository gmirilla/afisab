<?php

namespace Tests\Feature;

use App\Enums\InvestmentStatus;
use App\Enums\ProfitFrequency;
use App\Exceptions\BusinessRuleException;
use App\Models\Commodity;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\LedgerAccount;
use App\Models\TradeCycle;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Notifications\WithdrawalMatured;
use App\Notifications\WithdrawalRequestedAlert;
use App\Notifications\WithdrawalRequestReceived;
use App\Services\Ledger\LedgerService;
use App\Services\Payments\PaymentService;
use App\Services\Performance\DistributionService;
use App\Services\Performance\PerformanceService;
use App\Services\Performance\ProfitSchedule;
use App\Services\SubscriptionService;
use App\Services\Trading\CycleService;
use App\Services\Treasury\CapitalPayoutService;
use App\Services\Treasury\WithdrawalService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private WithdrawalService $withdrawals;

    private User $accounts;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
        $this->withdrawals = app(WithdrawalService::class);
        $this->accounts = User::factory()->create()->assignRole('accounts');
        $this->director = User::factory()->create()->assignRole('super-admin');
    }

    private function policy(ProfitFrequency $frequency = ProfitFrequency::Quarterly, string $name = 'Ada Obi', int $amount = 2_000_000_00): Investment
    {
        $user = User::factory()->create(['name' => $name])->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '12-month')->first(), $amount, $frequency, ['ip' => '1.1.1.1']);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh();
    }

    private function giveNotice(Investment $i, ?string $reason = null): WithdrawalRequest
    {
        return $this->withdrawals->request($i, $i->user, $reason);
    }

    // ---- giving notice ----

    public function test_notice_runs_for_the_period_frozen_on_the_policy(): void
    {
        $i = $this->policy();
        config(['afisab.policy.early_withdrawal_notice_months' => 6]); // the company changes the rule afterwards
        Notification::fake();
        $staffOnly = User::factory()->create()->assignRole('hr');

        $n = $this->giveNotice($i, 'School fees');

        $this->assertSame('2026-11-20', $n->notice_ends_on->toDateString(), 'the one month in the agreement, not the new six');
        $this->assertSame(WithdrawalRequest::REQUESTED, $n->status);
        $this->assertSame($i->maturity_date->toDateString(), $n->original_maturity_date->toDateString());
        $this->assertSame('School fees', $n->reason);
        $this->assertSame(InvestmentStatus::Active, $i->fresh()->status, 'still active, still earning, during the notice');
        Notification::assertSentTo($i->user, WithdrawalRequestReceived::class);
        Notification::assertSentTo($this->accounts, WithdrawalRequestedAlert::class);
        Notification::assertSentTo($this->director, WithdrawalRequestedAlert::class);
        Notification::assertNotSentTo($staffOnly, WithdrawalRequestedAlert::class);
        $this->assertDatabaseHas('activity_log', ['description' => 'Early withdrawal notice given', 'causer_id' => $i->user->id]);
    }

    public function test_notice_is_refused_when_it_makes_no_sense(): void
    {
        $active = $this->policy();
        $this->giveNotice($active);
        $pending = app(SubscriptionService::class)->subscribe($this->policy(name: 'Other')->user, InvestmentPlan::first(), 1_000_000_00);
        $nearEnd = $this->policy(name: 'Near End');
        $nearEnd->update(['maturity_date' => now()->addDays(10)]);

        foreach ([
            'already has a notice' => fn () => $this->giveNotice($active->fresh()),
            'not paid for yet' => fn () => $this->giveNotice($pending),
            'matures before the notice would end' => fn () => $this->giveNotice($nearEnd->fresh()),
        ] as $why => $attempt) {
            try {
                $attempt();
                $this->fail("a notice was accepted when the policy $why");
            } catch (BusinessRuleException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(1, WithdrawalRequest::count());
    }

    public function test_an_investor_gives_and_cancels_notice_from_their_own_page(): void
    {
        $i = $this->policy();
        $stranger = $this->policy(name: 'Stranger')->user;

        $this->actingAs($i->user)->get(route('investor.investments.show', $i))->assertOk()->assertSee('Early withdrawal')->assertSee("1 month' notice", false)->assertSee('Give notice of early withdrawal');

        $this->actingAs($i->user)->post(route('investor.withdrawal.store', $i), ['reason' => 'x'])->assertSessionHasErrors('understand');
        $this->actingAs($stranger)->post(route('investor.withdrawal.store', $i), ['understand' => '1'])->assertNotFound();
        $this->assertSame(0, WithdrawalRequest::count());

        $this->actingAs($i->user)->post(route('investor.withdrawal.store', $i), ['understand' => '1'])->assertSessionHasNoErrors()->assertSessionHas('status');
        $notice = WithdrawalRequest::firstOrFail();
        $this->actingAs($i->user)->get(route('investor.investments.show', $i))->assertSee('You gave notice on')->assertSee('20 Nov 2026')->assertSee('Cancel my notice');
        $this->actingAs($i->user)->post(route('investor.withdrawal.store', $i), ['understand' => '1'])->assertSessionHasErrors('withdrawal'); // not twice

        $this->actingAs($stranger)->delete(route('investor.withdrawal.cancel', $notice))->assertNotFound();
        $this->actingAs($i->user)->delete(route('investor.withdrawal.cancel', $notice))->assertSessionHasNoErrors();
        $this->assertSame(WithdrawalRequest::CANCELLED, $notice->fresh()->status);
        $this->actingAs($i->user)->post(route('investor.withdrawal.store', $i), ['understand' => '1'])->assertSessionHasNoErrors(); // can start again
        $this->assertSame(2, WithdrawalRequest::count());
    }

    // ---- maturing early ----

    public function test_the_policy_matures_early_once_the_notice_has_run(): void
    {
        $i = $this->policy(ProfitFrequency::Quarterly);
        $n = $this->giveNotice($i);

        try {
            $this->withdrawals->process($n, $this->accounts);
            $this->fail('matured before the notice ended');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('notice period runs until 20 Nov 2026', $e->getMessage());
        }

        $this->travelTo(Carbon::parse('2026-11-20 08:00'));
        Notification::fake();
        $done = $this->withdrawals->process($n->fresh(), $this->accounts);

        $this->assertSame(WithdrawalRequest::MATURED, $done->status);
        $this->assertSame($this->accounts->id, $done->matured_by);
        $fresh = $i->fresh();
        $this->assertSame(InvestmentStatus::Matured, $fresh->status);
        $this->assertSame('2026-11-20', $fresh->maturity_date->toDateString());
        $this->assertNotSame($n->original_maturity_date->toDateString(), $fresh->maturity_date->toDateString());
        Notification::assertSentTo($i->user, WithdrawalMatured::class);
        $this->assertDatabaseHas('activity_log', ['description' => "Investment matured early at the investor's request"]);

        // A quarterly policy is not due in November on its own, but the early maturity makes this its final run.
        $this->assertTrue(ProfitSchedule::isDue($fresh, Carbon::parse('2026-11-01')));
        $this->expectException(BusinessRuleException::class);
        $this->withdrawals->process($done, $this->accounts); // not twice
    }

    public function test_profit_held_back_for_the_frequency_is_settled_in_the_final_run(): void
    {
        $i = $this->policy(ProfitFrequency::AtMaturity);
        app(PerformanceService::class)->recordCycleResult('C1', [$i->id => $i->amount], 400_000_00);
        $n = $this->giveNotice($i);
        $this->travelTo(Carbon::parse('2026-11-20 08:00'));
        $this->assertSame(0, app(DistributionService::class)->calculate(now(), null, notify: false)->lines()->count(), 'a one-off policy is not due before it matures');

        $this->withdrawals->process($n, $this->accounts);
        $run = app(DistributionService::class)->calculate(now(), null, notify: false);

        $this->assertSame(1, $run->lines()->count());
        $this->assertSame(400_000_00, $run->lines()->first()->net_profit);
    }

    public function test_capital_still_in_a_cycle_holds_the_withdrawal_back_until_it_is_free(): void
    {
        $i = $this->policy();
        $sales = User::factory()->create()->assignRole('sales');
        $cycles = app(CycleService::class);
        $cycle = $cycles->open('Nov sesame', Commodity::first(), null, $sales);
        $cycles->allocate($cycle, $i, 1_000_000_00, $sales);
        $n = $this->giveNotice($i->fresh());

        $this->travelTo(Carbon::parse('2026-11-20 08:00'));
        $blockers = $this->withdrawals->blockers($n->fresh());
        $this->assertCount(1, $blockers);
        $this->assertStringContainsString($cycle->reference, $blockers[0]);
        $this->assertSame(['matured' => 0, 'waiting' => 1], $this->withdrawals->processDue());
        $this->assertSame(InvestmentStatus::Active, $i->fresh()->status);

        $cycles->cancel($cycle->fresh(), $sales); // the cycle comes to an end
        $this->assertSame([], $this->withdrawals->blockers($n->fresh()));
        $this->assertSame(['matured' => 1, 'waiting' => 0], $this->withdrawals->processDue());
        $this->assertSame(InvestmentStatus::Matured, $i->fresh()->status);
        $this->assertSame(['matured' => 0, 'waiting' => 0], $this->withdrawals->processDue(), 'a second run does nothing');
    }

    public function test_capital_that_has_given_notice_is_not_put_into_new_cycles(): void
    {
        $i = $this->policy();
        $other = $this->policy(name: 'Free Policy');
        $this->giveNotice($i);
        $sales = User::factory()->create()->assignRole('sales');
        $cycles = app(CycleService::class);

        $plan = $cycles->capitalPlan('all');
        $this->assertSame([$other->id], collect($plan['rows'])->pluck('investment.id')->all());

        $cycle = $cycles->open('Cycle', Commodity::first(), null, $sales);
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('given notice of early withdrawal');
        $cycles->allocate($cycle, $i->fresh(), 500_000_00, $sales);
    }

    public function test_notices_not_yet_due_are_left_alone_by_the_daily_job(): void
    {
        $this->giveNotice($this->policy());
        $this->giveNotice($this->policy(name: 'Second'));
        $this->travelTo(Carbon::parse('2026-11-19 08:00'));

        $this->artisan('afisab:process-withdrawals')->expectsOutputToContain('Matured 0, still waiting 0')->assertSuccessful();

        $this->travelTo(Carbon::parse('2026-11-20 08:00'));
        $this->artisan('afisab:process-withdrawals')->expectsOutputToContain('Matured 2, still waiting 0')->assertSuccessful();
        $this->assertSame(2, Investment::where('status', InvestmentStatus::Matured)->count());

        Artisan::call('schedule:list');
        $this->assertStringContainsString('afisab:process-withdrawals', Artisan::output());
    }

    // ---- from notice to money ----

    public function test_the_early_maturity_flows_through_the_normal_capital_payout_and_closes_the_request(): void
    {
        $i = $this->policy();
        app(LedgerService::class)->transfer(LedgerAccount::INVENTORY, LedgerAccount::MAIN_OPS, 50_000_000_00, 'Simulated trading proceeds');
        $n = $this->giveNotice($i);
        $this->travelTo(Carbon::parse('2026-11-20 08:00'));
        $this->withdrawals->process($n, $this->accounts);

        $payouts = app(CapitalPayoutService::class);
        $payout = $payouts->prepare($i->fresh(), $this->accounts);
        $payouts->approve($payout, $this->director);
        $other = User::factory()->create()->assignRole('accounts');
        $payouts->markPaid($payout->fresh(), 'NIP-CAP-1', $other);

        $this->assertSame(InvestmentStatus::PaidOut, $i->fresh()->status);
        $this->assertSame(WithdrawalRequest::COMPLETED, $n->fresh()->status);
        $this->assertNotNull($n->fresh()->completed_at);
        $this->actingAs($i->user)->get(route('investor.investments.show', $i))->assertSee('Your capital was returned on 20 Nov 2026');
    }

    // ---- staff ----

    public function test_staff_see_what_each_notice_is_waiting_for_and_can_mature_or_cancel_it(): void
    {
        $ready = $this->giveNotice($this->policy(name: 'Ready Rita'));
        $this->giveNotice($this->policy(name: 'Waiting Wale'));
        $this->travelTo(Carbon::parse('2026-11-20 08:00'));
        $sales = User::factory()->create()->assignRole('sales');
        $cycles = app(CycleService::class);
        $blocked = WithdrawalRequest::whereHas('investment.user', fn ($q) => $q->where('name', 'Waiting Wale'))->firstOrFail();
        // put Wale's capital in a cycle after the fact to show the blocker (allocation is refused once notice is given, so insert directly)
        $cycle = $cycles->open('Held', Commodity::first(), null, $sales);
        \App\Models\CycleAllocation::create(['trade_cycle_id' => $cycle->id, 'investment_id' => $blocked->investment_id, 'amount' => 500_000_00]);

        $page = $this->actingAs($this->accounts)->get(route('admin.withdrawals'))->assertOk();
        $page->assertSee('Ready Rita')->assertSee('Nothing: ready to mature')->assertSee($cycle->reference)->assertSee('Mature now');
        $this->actingAs($this->accounts)->get(route('admin.dashboard'))->assertSee('Early withdrawals ready to mature');

        $this->actingAs($this->accounts)->post(route('admin.withdrawals.process', $blocked))->assertSessionHasErrors('withdrawal');
        $this->actingAs($this->accounts)->post(route('admin.withdrawals.process', $ready))->assertSessionHasNoErrors();
        $this->assertSame(WithdrawalRequest::MATURED, $ready->fresh()->status);

        $this->actingAs($this->accounts)->post(route('admin.withdrawals.cancel', $blocked), [])->assertSessionHasErrors('note');
        $this->actingAs($this->accounts)->post(route('admin.withdrawals.cancel', $blocked), ['note' => 'Investor phoned to withdraw the request'])->assertSessionHasNoErrors();
        $this->assertSame(WithdrawalRequest::CANCELLED, $blocked->fresh()->status);
        $this->actingAs($this->accounts)->get(route('admin.withdrawals'))->assertSee('Investor phoned to withdraw the request')->assertSee('Matured early');
    }

    public function test_who_may_see_and_act_on_withdrawals(): void
    {
        $n = $this->giveNotice($this->policy());
        $auditor = User::factory()->create()->assignRole('auditor');

        foreach (['accounts' => $this->accounts, 'director' => $this->director, 'auditor' => $auditor] as $who => $user) {
            $this->actingAs($user)->get(route('admin.withdrawals'))->assertOk();
        }
        foreach (['hr', 'sales', 'procurement', 'compliance', 'investor'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->get(route('admin.withdrawals'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.withdrawals.process', $n))->assertForbidden();
        }
        $this->actingAs($auditor)->post(route('admin.withdrawals.process', $n))->assertForbidden();
        $this->actingAs($auditor)->post(route('admin.withdrawals.cancel', $n), ['note' => 'nope nope'])->assertForbidden();
        $this->assertSame(WithdrawalRequest::REQUESTED, $n->fresh()->status);

        $this->actingAs($this->accounts)->get(route('admin.investors.show', $n->investment->user))->assertSee('Withdrawal notice until 20 Nov 2026');
    }

    public function test_the_agreement_clause_and_the_notice_use_the_same_period(): void
    {
        $i = $this->policy();
        $i->update(['terms' => array_replace($i->terms, ['early_withdrawal_notice_months' => 2])]);

        $n = $this->giveNotice($i->fresh());

        $this->assertSame('2026-12-20', $n->notice_ends_on->toDateString());
        $context = app(\App\Services\Policy\PolicyDocumentService::class)->context($i->fresh()->load(['user.investorProfile', 'plan']), null);
        $this->assertSame(2, $context['notice_months']);
    }
}
