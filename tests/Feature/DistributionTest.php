<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\DistributionLine;
use App\Models\DistributionRun;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Notifications\DistributionNotice;
use App\Notifications\ProfitPaid;
use App\Services\Ledger\LedgerService;
use App\Services\Payments\PaymentService;
use App\Services\Performance\DistributionService;
use App\Services\Performance\PerformanceService;
use App\Services\Performance\ProfitSplit;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DistributionTest extends TestCase
{
    use RefreshDatabase;

    private DistributionService $distributions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->distributions = app(DistributionService::class);
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    }

    private function investment(int $amount = 1_000_000_00, string $name = 'Ada Obi'): Investment
    {
        $user = User::factory()->create(['name' => $name])->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved', 'bank_name' => 'Test Bank', 'account_number' => '0123456789', 'account_name' => $name]);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '12-month')->first(), $amount);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh();
    }

    private function record(Investment $i, int $net, string $cycle, ?int $capital = null): void
    {
        app(PerformanceService::class)->recordCycleResult($cycle, [$i->id => $capital ?? $i->amount], $net);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('super-admin');
    }

    private function accounts(): User
    {
        return User::factory()->create()->assignRole('accounts');
    }

    // ---- the split ----

    public function test_split_is_20_percent_retained_then_30_70_of_the_rest(): void
    {
        $split = (new ProfitSplit)->split(200_000_00);

        $this->assertSame(40_000_00, $split['retained']);
        $this->assertSame(160_000_00, $split['distributable']);
        $this->assertSame(48_000_00, $split['investor']);   // 30% of 160,000
        $this->assertSame(112_000_00, $split['company']);   // 70% of 160,000
    }

    public function test_split_parts_always_sum_to_the_net_profit(): void
    {
        foreach ([1, 7, 999, 123_457, 100_000_01, 987_654_321] as $net) {
            $s = (new ProfitSplit)->split($net);
            $this->assertSame($net, $s['retained'] + $s['investor'] + $s['company'], "net {$net}");
        }
    }

    public function test_no_profit_distributes_nothing(): void
    {
        foreach ([0, -50_000_00] as $net) {
            $s = (new ProfitSplit)->split($net);
            $this->assertSame([0, 0, 0], [$s['retained'], $s['investor'], $s['company']]);
        }
    }

    // ---- calculation ----

    public function test_run_sweeps_results_up_to_the_27th_and_nets_per_policy(): void
    {
        $a = $this->investment();
        $b = $this->investment(2_000_000_00, 'Bayo Ade');
        $this->record($a, 150_000_00, 'C-1');
        $this->record($a, -50_000_00, 'C-2');          // same month: nets to 100,000
        $this->record($b, 200_000_00, 'C-3');

        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);

        $this->assertSame('2026-10', $run->period);
        $this->assertSame('2026-10-27', $run->cutoff_date->toDateString());
        $this->assertSame('2026-10-31', $run->pay_by->toDateString());
        $this->assertSame(DistributionRun::CALCULATED, $run->status);

        $lineA = $run->lines()->where('investment_id', $a->id)->first();
        $this->assertSame(100_000_00, $lineA->net_profit);
        $this->assertSame(24_000_00, $lineA->investor_share); // (100,000 - 20,000) * 30%

        $this->assertSame(300_000_00, $run->total_net_profit);
        $this->assertSame(
            $run->total_net_profit,
            $run->total_retained + $run->total_investor + $run->total_company,
        );
    }

    public function test_results_after_the_cutoff_roll_into_next_months_run(): void
    {
        $i = $this->investment();
        $this->travelTo(Carbon::parse('2026-10-28 09:00'));
        $this->record($i, 100_000_00, 'LATE');

        $october = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);
        $this->assertSame(0, $october->lines()->count());

        $november = $this->distributions->calculate(Carbon::parse('2026-11-01'), notify: false);
        $this->assertSame('2026-11-27', $november->cutoff_date->toDateString());
        $this->assertSame(1, $november->lines()->count());
    }

    public function test_result_recorded_on_the_27th_itself_is_included(): void
    {
        $i = $this->investment();
        $this->travelTo(Carbon::parse('2026-10-27 23:30'));
        $this->record($i, 100_000_00, 'LAST-MINUTE');

        $this->assertSame(1, $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false)->lines()->count());
    }

    public function test_february_cutoff_and_deadline(): void
    {
        $schedule = DistributionService::nextSchedule(Carbon::parse('2027-02-10'));

        $this->assertSame('2027-02-27', $schedule['cutoff']->toDateString());
        $this->assertSame('2027-02-28', $schedule['pay_by']->toDateString());
    }

    public function test_next_schedule_rolls_to_next_month_after_the_27th(): void
    {
        $this->assertSame('2026-10-27', DistributionService::nextSchedule(Carbon::parse('2026-10-14'))['cutoff']->toDateString());
        $this->assertSame('2026-10-31', DistributionService::nextSchedule(Carbon::parse('2026-10-14'))['pay_by']->toDateString());
        $next = DistributionService::nextSchedule(Carbon::parse('2026-10-28'));
        $this->assertSame('2026-11-27', $next['cutoff']->toDateString());
        $this->assertSame('2026-11-30', $next['pay_by']->toDateString());
    }

    public function test_recalculation_before_approval_picks_up_late_entries_without_duplicates(): void
    {
        $i = $this->investment();
        $this->record($i, 100_000_00, 'A');
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);

        $this->travelTo(Carbon::parse('2026-10-26 12:00'));
        $this->record($i, 50_000_00, 'B');
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);

        $this->assertSame(1, $run->lines()->count());
        $this->assertSame(150_000_00, $run->lines()->first()->net_profit);
        $this->assertSame(1, DistributionRun::count());
    }

    public function test_approved_run_cannot_be_recalculated(): void
    {
        $i = $this->investment();
        $this->record($i, 100_000_00, 'A');
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);
        $this->distributions->approve($run, $this->admin());

        $this->expectException(BusinessRuleException::class);
        $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);
    }

    public function test_calculation_notifies_approvers(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->distributions->calculate(Carbon::parse('2026-10-01'));

        Notification::assertSentTo($admin, DistributionNotice::class);
    }

    // ---- approval (maker-checker) and ledger ----

    public function test_the_person_who_calculated_cannot_approve(): void
    {
        $i = $this->investment();
        $this->record($i, 100_000_00, 'A');
        $maker = $this->admin();
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), $maker, notify: false);

        try {
            $this->distributions->approve($run, $maker);
            $this->fail('Maker approved their own run');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }

        $this->distributions->approve($run, $this->admin()); // a different person can
        $this->assertSame(DistributionRun::APPROVED, $run->fresh()->status);
    }

    public function test_approval_books_the_liability_and_updates_the_investors_position(): void
    {
        $i = $this->investment();
        $this->record($i, 200_000_00, 'A');
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);

        $position = app(PerformanceService::class)->position($i);
        $this->assertSame(0, $position['payable']); // nothing before approval

        $this->distributions->approve($run, $this->admin());

        $ledger = app(LedgerService::class);
        $this->assertSame(48_000_00, $ledger->balance(LedgerAccount::INVESTOR_PROFIT_PAYABLE));
        $this->assertSame(48_000_00, $ledger->balance(LedgerAccount::INVESTOR_PROFIT_PAYABLE, $i));

        $position = app(PerformanceService::class)->position($i);
        $this->assertSame(48_000_00, $position['payable']);
        $this->assertSame(1_048_000_00, $position['current_value']);
        $this->assertSame(0, $position['pending_entries']);
    }

    public function test_a_loss_month_creates_no_liability(): void
    {
        $i = $this->investment();
        $this->record($i, -80_000_00, 'LOSS');
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);
        $this->distributions->approve($run, $this->admin());

        $line = $run->lines()->first();
        $this->assertSame(DistributionLine::NONE, $line->status);
        $this->assertSame(0, app(LedgerService::class)->balance(LedgerAccount::INVESTOR_PROFIT_PAYABLE));
        $this->assertSame(DistributionRun::PAID, $run->fresh()->status); // nothing to pay, so complete
    }

    // ---- payment ----

    public function test_paying_a_line_moves_cash_closes_the_liability_and_notifies(): void
    {
        Notification::fake();
        $i = $this->investment();
        $this->record($i, 200_000_00, 'A');
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);
        $this->distributions->approve($run, $this->admin());

        $ledger = app(LedgerService::class);
        $cashBefore = $ledger->balance(LedgerAccount::MAIN_OPS);

        $line = $this->distributions->markLinePaid($run->lines()->first(), 'NIP-123', $this->accounts());

        $this->assertSame(DistributionLine::PAID, $line->status);
        $this->assertSame($cashBefore - 48_000_00, $ledger->balance(LedgerAccount::MAIN_OPS));
        $this->assertSame(0, $ledger->balance(LedgerAccount::INVESTOR_PROFIT_PAYABLE));
        $this->assertSame(DistributionRun::PAID, $run->fresh()->status);
        Notification::assertSentTo($i->user, ProfitPaid::class);

        $position = app(PerformanceService::class)->position($i);
        $this->assertSame(48_000_00, $position['paid']);
        $this->assertSame(1_000_000_00, $position['current_value']); // paid profit has left the policy
    }

    public function test_a_line_cannot_be_paid_twice_or_without_a_reference(): void
    {
        $i = $this->investment();
        $this->record($i, 200_000_00, 'A');
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);
        $this->distributions->approve($run, $this->admin());
        $line = $run->lines()->first();

        try {
            $this->distributions->markLinePaid($line, '  ', $this->accounts());
            $this->fail('Paid without a reference');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }

        $this->distributions->markLinePaid($line, 'NIP-1', $this->accounts());
        $this->expectException(BusinessRuleException::class);
        $this->distributions->markLinePaid($line, 'NIP-1', $this->accounts());
    }

    public function test_an_unapproved_run_cannot_be_paid(): void
    {
        $i = $this->investment();
        $this->record($i, 200_000_00, 'A');
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);

        $this->expectException(BusinessRuleException::class);
        $this->distributions->markAllPaid($run, 'BATCH', $this->accounts());
    }

    public function test_bank_export_lists_payable_investors_and_neutralises_formulas(): void
    {
        $i = $this->investment(name: '=HYPERLINK("x")');
        $this->record($i, 200_000_00, 'A');
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);
        $this->distributions->approve($run, $this->admin());

        $rows = $this->distributions->exportRows($run);

        $this->assertSame('Account number', $rows[0][3]);
        $this->assertSame('0123456789', $rows[1][3]);
        $this->assertSame('48000.00', $rows[1][5]);
        $this->assertStringStartsWith("'=", $rows[1][1]);
    }

    // ---- access control ----

    public function test_accounts_can_calculate_and_view_but_only_a_director_can_approve(): void
    {
        $i = $this->investment();
        $this->record($i, 200_000_00, 'A');
        $accounts = $this->accounts();

        $this->actingAs($accounts)->post(route('admin.distributions.calculate'), ['month' => '2026-10'])->assertSessionHasNoErrors();
        $run = DistributionRun::firstOrFail();
        $this->actingAs($accounts)->get(route('admin.distributions.show', $run))->assertOk()->assertSee('Retained');

        $this->actingAs($accounts)->post(route('admin.distributions.approve', $run))->assertForbidden();
        $this->actingAs($this->admin())->post(route('admin.distributions.approve', $run))->assertRedirect();
        $this->assertSame(DistributionRun::APPROVED, $run->fresh()->status);

        $this->actingAs($accounts)->get(route('admin.distributions.export', $run))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_investors_and_other_staff_cannot_reach_distributions(): void
    {
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);

        $this->actingAs($this->investment()->user)->get(route('admin.distributions'))->assertForbidden();
        $this->actingAs(User::factory()->create()->assignRole('hr'))->get(route('admin.distributions.show', $run))->assertForbidden();
    }

    public function test_export_is_refused_before_approval(): void
    {
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);

        $this->actingAs($this->accounts())->get(route('admin.distributions.export', $run))->assertStatus(422);
    }

    // ---- scheduling and reminders ----

    public function test_the_27th_run_and_daily_reminder_are_scheduled(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('afisab:run-distribution')
            ->expectsOutputToContain('afisab:distribution-reminders')
            ->assertSuccessful();
    }

    public function test_a_missed_calculation_is_caught_up_after_the_cutoff(): void
    {
        Notification::fake();
        $i = $this->investment();
        $this->record($i, 100_000_00, 'A');

        $this->travelTo(Carbon::parse('2026-10-28 09:00')); // the 27th job never ran
        $this->assertSame(['calculated (catch-up)', 'approval reminder'], $this->distributions->reminders());
        $this->assertSame(1, DistributionRun::where('period', '2026-10')->count());
    }

    public function test_approval_reminder_is_sent_once_a_day(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);

        $this->travelTo(Carbon::parse('2026-10-28 09:00'));
        $this->assertSame(['approval reminder'], $this->distributions->reminders());
        $this->assertSame([], $this->distributions->reminders()); // same day: no repeat

        $this->travelTo(Carbon::parse('2026-10-29 09:00'));
        $this->assertSame(['approval reminder'], $this->distributions->reminders());
        Notification::assertSentToTimes($admin, DistributionNotice::class, 2);
    }

    public function test_payment_reminder_goes_to_accounts_near_the_deadline(): void
    {
        Notification::fake();
        $accounts = $this->accounts();
        $i = $this->investment();
        $this->record($i, 100_000_00, 'A');
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);
        $this->distributions->approve($run, $this->admin());

        $this->travelTo(Carbon::parse('2026-10-29 09:00'));
        $this->assertSame(['payment reminder'], $this->distributions->reminders());
        Notification::assertSentTo($accounts, DistributionNotice::class);
    }

    public function test_an_unpaid_run_is_flagged_overdue_after_month_end(): void
    {
        $i = $this->investment();
        $this->record($i, 100_000_00, 'A');
        $run = $this->distributions->calculate(Carbon::parse('2026-10-01'), notify: false);

        $this->assertFalse($run->isOverdue());
        $this->travelTo(Carbon::parse('2026-11-01 08:00'));
        $this->assertTrue($run->fresh()->isOverdue());
    }
}
