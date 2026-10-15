<?php

namespace Tests\Feature;

use App\Enums\InvestmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BankReconciliation;
use App\Models\CapitalPayout;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Notifications\CapitalPaid;
use App\Notifications\CapitalPayoutReady;
use App\Services\Ledger\LedgerService;
use App\Services\Payments\PaymentService;
use App\Services\Performance\DistributionService;
use App\Services\Performance\PerformanceService;
use App\Services\SubscriptionService;
use App\Services\Treasury\CapitalPayoutService;
use App\Services\Treasury\TreasuryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TreasuryTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->ledger = app(LedgerService::class);
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    }

    private function investment(int $amount = 1_000_000_00, string $slug = '3-month', string $name = 'Ada Obi'): Investment
    {
        $user = User::factory()->create(['name' => $name])->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', $slug)->first(), $amount);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh();
    }

    private function matured(int $amount = 1_000_000_00): Investment
    {
        $investment = $this->investment($amount);
        $investment->update(['status' => InvestmentStatus::Matured]);

        return $investment->fresh();
    }

    /** Simulates trading proceeds returning to Main Operations (purchases and sales arrive in milestone 5). */
    private function topUp(int $amount = 100_000_00): void
    {
        $this->ledger->transfer(LedgerAccount::INVENTORY, LedgerAccount::MAIN_OPS, $amount, 'Simulated sales proceeds');
    }

    private function staff(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    // ---- ledger reads ----

    public function test_balances_by_subject_match_per_policy_balances(): void
    {
        $a = $this->investment(1_000_000_00);
        $b = $this->investment(2_000_000_00, name: 'Bayo Ade');

        $bySubject = $this->ledger->balancesBySubject(LedgerAccount::MAIN_OPS, 'investment');

        $this->assertSame($this->ledger->balance(LedgerAccount::MAIN_OPS, $a), $bySubject[$a->id]);
        $this->assertSame($this->ledger->balance(LedgerAccount::MAIN_OPS, $b), $bySubject[$b->id]);
        $this->assertSame(950_000_00, $bySubject[$a->id]);
    }

    public function test_balance_as_of_a_date_ignores_later_entries(): void
    {
        $this->investment();
        $afterFirst = now();

        $this->travelTo(now()->addDays(5));
        $this->investment(500_000_00, name: 'Late Investor');

        $this->assertSame(950_000_00, $this->ledger->balance(LedgerAccount::MAIN_OPS, null, $afterFirst));
        $this->assertSame(950_000_00 + 475_000_00, $this->ledger->balance(LedgerAccount::MAIN_OPS));
    }

    // ---- funds overview ----

    public function test_overview_reports_funds_and_what_is_due(): void
    {
        $near = $this->investment(1_000_000_00);                    // matures in 3 months
        $soon = $this->investment(2_000_000_00, name: 'Soon');
        $soon->update(['maturity_date' => now()->addDays(10)]);     // inside the 30-day window

        $o = app(TreasuryService::class)->overview();

        $this->assertSame(2_850_000_00, $o['main_ops']);
        $this->assertSame(150_000_00, $o['risk_fund']);
        $this->assertSame(3_000_000_00, $o['investor_capital_held']);
        $this->assertSame(2_000_000_00, $o['principal_due']);
        $this->assertSame(2_850_000_00 - 2_000_000_00, $o['available']);
    }

    public function test_available_goes_negative_when_obligations_exceed_cash(): void
    {
        $i = $this->investment(1_000_000_00);
        $i->update(['maturity_date' => now()->addDays(5)]); // principal 1,000,000 due but Main Ops holds only 950,000

        $this->assertSame(-50_000_00, app(TreasuryService::class)->overview()['available']);
    }

    public function test_policy_rows_show_where_each_policys_money_sits(): void
    {
        $i = $this->investment(1_000_000_00);

        $row = app(TreasuryService::class)->policyRows()->first();

        $this->assertSame($i->id, $row['investment']->id);
        $this->assertSame(1_000_000_00, $row['capital_held']);
        $this->assertSame(950_000_00, $row['in_main_ops']);
        $this->assertSame(50_000_00, $row['in_risk_fund']);
        $this->assertSame(0, $row['profit_payable']);
    }

    public function test_maturity_schedule_groups_principal_by_month(): void
    {
        $this->investment(1_000_000_00);
        $this->investment(500_000_00, name: 'Two');
        $this->investment(700_000_00, slug: '12-month', name: 'Three');

        $schedule = app(TreasuryService::class)->maturitySchedule();

        $this->assertSame(2, $schedule['2027-01']['count']);
        $this->assertSame(1_500_000_00, $schedule['2027-01']['amount']);
        $this->assertSame(700_000_00, $schedule['2027-10']['amount']);
    }

    // ---- cash sufficiency guard ----

    public function test_a_payment_larger_than_main_operations_cash_is_refused(): void
    {
        $this->investment(1_000_000_00); // Main Ops 950,000

        $this->expectException(BusinessRuleException::class);
        app(TreasuryService::class)->assertCanPay(950_000_01);
    }

    public function test_profit_payment_is_blocked_when_cash_is_insufficient(): void
    {
        $i = $this->investment();
        app(PerformanceService::class)->recordCycleResult('C', [$i->id => $i->amount], 200_000_00);
        $dist = app(DistributionService::class);
        $run = $dist->calculate(Carbon::parse('2026-10-01'), notify: false);
        $dist->approve($run, $this->staff('super-admin'));

        // Move all cash out of Main Operations.
        $this->ledger->transfer(LedgerAccount::MAIN_OPS, LedgerAccount::DOMICILIARY, $this->ledger->balance(LedgerAccount::MAIN_OPS), 'drain');

        $this->expectException(BusinessRuleException::class);
        $dist->markLinePaid($run->lines()->first(), 'NIP-1', $this->staff('accounts'));
    }

    // ---- capital payouts ----

    public function test_only_a_matured_investment_can_be_prepared_for_payout(): void
    {
        $active = $this->investment();

        $this->expectException(BusinessRuleException::class);
        app(CapitalPayoutService::class)->prepare($active, $this->staff('accounts'));
    }

    public function test_full_capital_payout_flow_returns_principal_and_closes_the_policy(): void
    {
        Notification::fake();
        $admin = $this->staff('super-admin');
        $accounts = $this->staff('accounts');
        $i = $this->matured();
        $this->topUp();
        $service = app(CapitalPayoutService::class);

        $payout = $service->prepare($i, $accounts);
        Notification::assertSentTo($admin, CapitalPayoutReady::class);

        $service->approve($payout, $admin);

        $cashBefore = $this->ledger->balance(LedgerAccount::MAIN_OPS);
        $service->markPaid($payout, 'NIP-CAP-1', $accounts);

        $this->assertSame($cashBefore - 1_000_000_00, $this->ledger->balance(LedgerAccount::MAIN_OPS));
        $this->assertSame(0, $this->ledger->balance(LedgerAccount::INVESTOR_FUNDS, $i)); // capital owed is cleared
        $this->assertSame(InvestmentStatus::PaidOut, $i->fresh()->status);
        $this->assertSame(CapitalPayout::PAID, $payout->fresh()->status);
        Notification::assertSentTo($i->user, CapitalPaid::class);
    }

    public function test_payout_must_be_approved_by_someone_other_than_the_preparer(): void
    {
        $admin = $this->staff('super-admin');
        $payout = app(CapitalPayoutService::class)->prepare($this->matured(), $admin);

        $this->expectException(BusinessRuleException::class);
        app(CapitalPayoutService::class)->approve($payout, $admin);
    }

    public function test_an_unapproved_payout_cannot_be_paid(): void
    {
        $payout = app(CapitalPayoutService::class)->prepare($this->matured(), $this->staff('accounts'));

        $this->expectException(BusinessRuleException::class);
        app(CapitalPayoutService::class)->markPaid($payout, 'NIP', $this->staff('accounts'));
    }

    public function test_a_payout_cannot_be_prepared_or_paid_twice(): void
    {
        $i = $this->matured();
        $this->topUp();
        $accounts = $this->staff('accounts');
        $service = app(CapitalPayoutService::class);
        $payout = $service->prepare($i, $accounts);

        try {
            $service->prepare($i, $accounts);
            $this->fail('Second payout prepared');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }

        $service->approve($payout, $this->staff('super-admin'));
        $service->markPaid($payout, 'NIP', $accounts);

        $this->expectException(BusinessRuleException::class);
        $service->markPaid($payout, 'NIP', $accounts);
    }

    public function test_a_rejected_payout_can_be_prepared_again(): void
    {
        $i = $this->matured();
        $accounts = $this->staff('accounts');
        $service = app(CapitalPayoutService::class);

        $service->reject($service->prepare($i, $accounts), $this->staff('super-admin'), 'Wrong bank details');
        $second = $service->prepare($i, $accounts);

        $this->assertSame(CapitalPayout::PREPARED, $second->status);
    }

    public function test_capital_payout_is_blocked_when_cash_is_insufficient(): void
    {
        $i = $this->matured(); // Main Ops holds only 950,000 of the 1,000,000 principal (5% sits in the Risk Fund)
        $accounts = $this->staff('accounts');
        $service = app(CapitalPayoutService::class);
        $payout = $service->prepare($i, $accounts);
        $service->approve($payout, $this->staff('super-admin'));

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Insufficient funds');
        $service->markPaid($payout, 'NIP', $accounts);
    }

    // ---- reconciliation ----

    public function test_reconciliation_records_balanced_and_unbalanced_statements(): void
    {
        $this->investment();
        $accounts = $this->staff('accounts');
        $main = LedgerAccount::byCode(LedgerAccount::MAIN_OPS);
        $treasury = app(TreasuryService::class);

        $ok = $treasury->reconcile($main, now(), 950_000_00, null, $accounts);
        $this->assertTrue($ok->isBalanced());

        $bad = $treasury->reconcile($main, now(), 940_000_00, 'Unexplained', $accounts);
        $this->assertSame(-10_000_00, $bad->difference);
        $this->assertSame(2, BankReconciliation::count());
    }

    public function test_reconciliation_uses_the_ledger_balance_at_the_statement_date(): void
    {
        $this->investment();
        $this->travelTo(now()->addDays(3));
        $this->investment(500_000_00, name: 'Later');

        $rec = app(TreasuryService::class)->reconcile(
            LedgerAccount::byCode(LedgerAccount::MAIN_OPS), Carbon::parse('2026-10-21'), 950_000_00, null, $this->staff('accounts'),
        );

        $this->assertSame(950_000_00, $rec->ledger_balance); // second deposit (23 Oct) is after the statement date
        $this->assertTrue($rec->isBalanced());
    }

    // ---- screens and access control ----

    public function test_accounts_staff_can_use_the_treasury_screens(): void
    {
        $a = $this->investment();
        $b = $this->investment(2_000_000_00, name: 'Bayo Ade');
        $accounts = $this->staff('accounts');

        $this->actingAs($accounts)->get(route('admin.funds'))->assertOk()->assertSee('Funds by investment policy')->assertSee($a->reference);
        $this->actingAs($accounts)->get(route('admin.payouts'))->assertOk();
        $this->actingAs($accounts)->get(route('admin.reconciliation'))->assertOk();

        $this->actingAs($accounts)->get(route('admin.ledger.account', 'MAIN_OPS', ['policy' => $a->reference]).'?policy='.$a->reference)
            ->assertOk()->assertSee($a->reference)->assertDontSee($b->reference);

        $this->actingAs($accounts)->post(route('admin.reconciliation.store'), [
            'account' => 'MAIN_OPS', 'as_of' => now()->toDateString(), 'statement_balance' => '2850000',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(BankReconciliation::first()->isBalanced());
    }

    public function test_reconciliation_rejects_non_bank_accounts_and_future_dates(): void
    {
        $accounts = $this->staff('accounts');

        $this->actingAs($accounts)->post(route('admin.reconciliation.store'), [
            'account' => 'INVENTORY', 'as_of' => now()->toDateString(), 'statement_balance' => '1',
        ])->assertSessionHasErrors('account');

        $this->actingAs($accounts)->post(route('admin.reconciliation.store'), [
            'account' => 'MAIN_OPS', 'as_of' => now()->addDay()->toDateString(), 'statement_balance' => '1',
        ])->assertSessionHasErrors('as_of');
    }

    public function test_auditor_can_view_but_not_change_anything(): void
    {
        $i = $this->matured();
        $auditor = $this->staff('auditor');

        $this->actingAs($auditor)->get(route('admin.funds'))->assertOk();
        $this->actingAs($auditor)->get(route('admin.ledger.account', 'MAIN_OPS'))->assertOk();
        $this->actingAs($auditor)->get(route('admin.reconciliation'))->assertOk();
        $this->actingAs($auditor)->post(route('admin.reconciliation.store'), ['account' => 'MAIN_OPS', 'as_of' => now()->toDateString(), 'statement_balance' => '1'])->assertForbidden();
        $this->actingAs($auditor)->post(route('admin.payouts.prepare', $i))->assertForbidden();
    }

    public function test_other_roles_and_investors_cannot_see_funds(): void
    {
        $i = $this->investment();

        $this->actingAs($this->staff('hr'))->get(route('admin.funds'))->assertForbidden();
        $this->actingAs($this->staff('hr'))->get(route('admin.ledger.account', 'MAIN_OPS'))->assertForbidden();
        $this->actingAs($i->user)->get(route('admin.funds'))->assertForbidden();
        $this->actingAs($i->user)->get(route('admin.payouts'))->assertForbidden();
    }

    public function test_payout_http_flow_enforces_roles(): void
    {
        $i = $this->matured();
        $accounts = $this->staff('accounts');
        $admin = $this->staff('super-admin');

        $this->actingAs($accounts)->post(route('admin.payouts.prepare', $i))->assertRedirect();
        $payout = CapitalPayout::firstOrFail();

        $this->actingAs($accounts)->post(route('admin.payouts.approve', $payout))->assertForbidden(); // accounts cannot approve
        $this->actingAs($admin)->post(route('admin.payouts.approve', $payout))->assertRedirect();
        $this->assertSame(CapitalPayout::APPROVED, $payout->fresh()->status);

        $this->actingAs($admin)->get(route('admin.payouts'))->assertOk()->assertSee($i->reference);
    }
}
