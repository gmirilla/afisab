<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Commodity;
use App\Models\Employee;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\LedgerAccount;
use App\Models\PayrollRun;
use App\Models\RiskLimit;
use App\Models\Supplier;
use App\Models\TakafulPolicy;
use App\Models\User;
use App\Services\Hr\PayrollService;
use App\Services\Ledger\LedgerService;
use App\Services\Payments\PaymentService;
use App\Services\Reports\ReportService;
use App\Services\Risk\RiskService;
use App\Services\Risk\TakafulService;
use App\Services\SubscriptionService;
use App\Services\Trading\CycleService;
use App\Services\Trading\PurchaseService;
use App\Support\Csv;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GovernanceTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    private User $admin;

    private User $accounts;

    private User $hr;

    private User $sales;

    private User $procurement;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-12 10:00'));
        $this->ledger = app(LedgerService::class);

        $this->admin = User::factory()->create()->assignRole('super-admin');
        $this->accounts = User::factory()->create()->assignRole('accounts');
        $this->hr = User::factory()->create()->assignRole('hr');
        $this->sales = User::factory()->create()->assignRole('sales');
        $this->procurement = User::factory()->create()->assignRole('procurement');
        $this->auditor = User::factory()->create()->assignRole('auditor');
    }

    private function investment(int $amount = 1_000_000_00, string $name = 'Ada Obi'): Investment
    {
        $user = User::factory()->create(['name' => $name])->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '12-month')->first(), $amount);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh();
    }

    private function supplier(string $name = 'Benue Aggregators'): Supplier
    {
        return Supplier::create(['name' => $name, 'type' => 'aggregator', 'is_active' => true]);
    }

    private function risk(string $key, int $value, bool $active = true): void
    {
        app(RiskService::class)->save($key, $value, $active, $this->admin);
    }

    // ---- risk limits ----

    public function test_limits_are_seeded_inactive_so_nothing_is_enforced_until_the_owner_switches_them_on(): void
    {
        $this->assertSame(5, RiskLimit::count());
        $this->assertSame(0, RiskLimit::where('is_active', true)->count());

        $investment = $this->investment();
        $cycle = app(CycleService::class)->open('Big', Commodity::first(), null, $this->sales);
        $this->assertSame(1_000_000_00, app(CycleService::class)->allocate($cycle, $investment, 1_000_000_00, $this->sales)->amount); // 100% deployed, no limit
    }

    public function test_deployment_limit_keeps_a_liquidity_buffer(): void
    {
        $this->risk('max_deployment_bps', 8_500);
        $investment = $this->investment(1_000_000_00);
        $cycle = app(CycleService::class)->open('A', Commodity::first(), null, $this->sales);

        try {
            app(CycleService::class)->allocate($cycle, $investment, 900_000_00, $this->sales); // 90% > 85%
            $this->fail('Allocated past the deployment limit');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('limit is 85.0%', $e->getMessage());
        }

        $this->assertSame(850_000_00, app(CycleService::class)->allocate($cycle, $investment, 850_000_00, $this->sales)->amount); // exactly at the limit is allowed
    }

    public function test_cycle_size_limit_is_enforced_on_allocation(): void
    {
        $this->risk('max_cycle_budget', 500_000_00);
        $investment = $this->investment(1_000_000_00);
        $cycle = app(CycleService::class)->open('A', Commodity::first(), null, $this->sales);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('may not exceed');
        app(CycleService::class)->allocate($cycle, $investment, 600_000_00, $this->sales);
    }

    public function test_supplier_exposure_limit_is_enforced_at_approval(): void
    {
        $this->risk('max_supplier_exposure', 700_000_00);
        $investment = $this->investment();
        $cycle = app(CycleService::class)->open('A', Commodity::first(), null, $this->sales);
        app(CycleService::class)->allocate($cycle, $investment, 900_000_00, $this->sales);
        $supplier = $this->supplier();
        $service = app(PurchaseService::class);

        $first = $service->propose($cycle, $supplier, 10_000, 5_000, $this->procurement); // 500,000
        $service->approve($first, $this->admin);
        $second = $service->propose($cycle, $supplier, 5_000, 5_000, $this->procurement); // +250,000 = 750,000 > 700,000

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Benue Aggregators');
        $service->approve($second, $this->admin);
    }

    public function test_commodity_exposure_limit_is_enforced_at_approval(): void
    {
        $this->risk('max_commodity_exposure', 400_000_00);
        $investment = $this->investment();
        $cycle = app(CycleService::class)->open('A', Commodity::where('name', 'Maize')->first(), null, $this->sales);
        app(CycleService::class)->allocate($cycle, $investment, 900_000_00, $this->sales);
        $purchase = app(PurchaseService::class)->propose($cycle, $this->supplier(), 10_000, 5_000, $this->procurement); // 500,000

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Maize');
        app(PurchaseService::class)->approve($purchase, $this->admin);
    }

    public function test_director_threshold_set_in_the_database_overrides_the_default(): void
    {
        $this->risk('purchase_director_threshold', 100_000_00);
        $investment = $this->investment();
        $cycle = app(CycleService::class)->open('A', Commodity::first(), null, $this->sales);
        app(CycleService::class)->allocate($cycle, $investment, 900_000_00, $this->sales);
        $purchase = app(PurchaseService::class)->propose($cycle, $this->supplier(), 4_000, 5_000, $this->procurement); // 200,000 > 100,000

        try {
            app(PurchaseService::class)->approve($purchase, $this->accounts);
            $this->fail('Accounts approved above the Director threshold');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Director', $e->getMessage());
        }
        $this->assertSame('approved', app(PurchaseService::class)->approve($purchase, $this->admin)->status);
    }

    public function test_limit_values_are_validated(): void
    {
        $risk = app(RiskService::class);

        try {
            $risk->save('max_cycle_budget', null, true, $this->admin);
            $this->fail('Active limit without a value');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }
        try {
            $risk->save('max_deployment_bps', 10_001, true, $this->admin);
            $this->fail('More than 100% allowed');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(BusinessRuleException::class);
        $risk->save('made_up', 1, true, $this->admin);
    }

    public function test_only_a_director_can_change_limits_but_accounts_can_see_the_risk_page(): void
    {
        $payload = ['limits' => ['max_cycle_budget' => ['value' => '250000', 'active' => '1'], 'max_deployment_bps' => ['value' => '80', 'active' => '1']]];

        $this->actingAs($this->accounts)->get(route('admin.risk'))->assertOk()->assertSee('Risk controls');
        $this->actingAs($this->accounts)->post(route('admin.risk.limits'), $payload)->assertForbidden();
        $this->actingAs($this->sales)->get(route('admin.risk'))->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.risk.limits'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(250_000_00, app(RiskService::class)->limit('max_cycle_budget'));
        $this->assertSame(8_000, app(RiskService::class)->limit('max_deployment_bps')); // 80% stored as basis points
    }

    // ---- takaful ----

    public function test_a_takaful_premium_is_paid_from_the_risk_fund(): void
    {
        $this->investment(); // Risk Fund 50,000
        $service = app(TakafulService::class);
        $policy = $service->create([
            'provider' => 'Noor Takaful', 'policy_number' => 'TK-001', 'description' => 'Warehouse stock cover',
            'premium' => 30_000_00, 'cover_amount' => 50_000_000_00, 'start_date' => '2026-10-01', 'end_date' => '2027-09-30',
        ], $this->accounts);

        $service->payPremium($policy, 'NIP-TK1', $this->accounts);

        $this->assertSame(20_000_00, $this->ledger->balance(LedgerAccount::RISK_FUND));
        $this->assertSame(30_000_00, $this->ledger->balance(LedgerAccount::INSURANCE_EXPENSE));
        $this->assertSame('paid', $policy->fresh()->premium_status);

        $this->expectException(BusinessRuleException::class); // not twice
        $service->payPremium($policy, 'NIP-TK1', $this->accounts);
    }

    public function test_a_premium_larger_than_the_risk_fund_is_refused(): void
    {
        $this->investment(); // Risk Fund 50,000
        $service = app(TakafulService::class);
        $policy = $service->create([
            'provider' => 'Noor Takaful', 'policy_number' => 'TK-002', 'description' => 'x',
            'premium' => 60_000_00, 'cover_amount' => 1_000_000_00, 'start_date' => '2026-10-01', 'end_date' => '2027-09-30',
        ], $this->accounts);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Risk Fund holds');
        $service->payPremium($policy, 'NIP', $this->accounts);
    }

    public function test_the_risk_dashboard_warns_about_expiring_takaful_cover(): void
    {
        TakafulPolicy::create([
            'provider' => 'Noor Takaful', 'policy_number' => 'TK-SOON', 'description' => 'Cover', 'premium' => 1_000_00, 'cover_amount' => 10_000_000_00,
            'start_date' => '2025-11-01', 'end_date' => '2026-10-25', 'premium_status' => 'paid',
        ]);

        $this->actingAs($this->accounts)->get(route('admin.risk'))->assertOk()->assertSee('TK-SOON')->assertSee('expires on 25 Oct 2026');
    }

    public function test_the_risk_dashboard_reports_deployment_and_exposure(): void
    {
        $investment = $this->investment();
        $cycle = app(CycleService::class)->open('A', Commodity::first(), null, $this->sales);
        app(CycleService::class)->allocate($cycle, $investment, 500_000_00, $this->sales);
        $dash = app(RiskService::class)->dashboard();

        $this->assertSame(5_000, $dash['deployment_bps']); // 500k of 1M
        $this->assertSame(500, $dash['risk_cover_bps']);   // Risk Fund 50k of 1M held
    }

    // ---- payroll ----

    private function employee(string $name = 'Chika Eze', int $salary = 200_000_00): Employee
    {
        return Employee::create(['name' => $name, 'basic_salary' => $salary, 'is_active' => true, 'bank_name' => 'Test Bank', 'account_number' => '0123456789', 'account_name' => $name]);
    }

    public function test_payroll_combines_salary_allowances_commission_and_deductions(): void
    {
        $chika = $this->employee('Chika Eze', 200_000_00);
        $bayo = $this->employee('Bayo Ade', 150_000_00);
        $service = app(PayrollService::class);
        $service->addAdjustment($chika, '2026-10', 'allowance', 'Transport', 20_000_00, $this->hr);
        $service->addAdjustment($chika, '2026-10', 'commission', 'Q3 sales', 50_000_00, $this->hr);
        $service->addAdjustment($chika, '2026-10', 'deduction', 'PAYE', 30_000_00, $this->hr);

        $run = $service->prepare('2026-10', $this->hr);

        $this->assertSame(220_000_00 + 50_000_00 + 150_000_00, $run->total_gross);    // 200+20+50 and 150
        $this->assertSame(30_000_00, $run->total_deductions);
        $this->assertSame(390_000_00, $run->total_net);
        $line = $run->lines()->where('employee_id', $chika->id)->first();
        $this->assertSame(240_000_00, $line->net); // 270 - 30
    }

    public function test_deductions_cannot_exceed_gross_pay_and_inactive_staff_are_skipped(): void
    {
        $chika = $this->employee('Chika Eze', 100_000_00);
        $this->employee('Left Company', 500_000_00)->update(['is_active' => false]);
        $service = app(PayrollService::class);
        $service->addAdjustment($chika, '2026-10', 'deduction', 'Loan', 150_000_00, $this->hr);

        try {
            $service->prepare('2026-10', $this->hr);
            $this->fail('Deductions above gross accepted');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('exceed their gross', $e->getMessage());
        }

        $service->removeAdjustment($chika->adjustments()->first(), $this->hr);
        $this->assertSame(100_000_00, $service->prepare('2026-10', $this->hr)->total_gross);
    }

    public function test_payroll_needs_employees(): void
    {
        $this->expectException(BusinessRuleException::class);
        app(PayrollService::class)->prepare('2026-10', $this->hr);
    }

    public function test_payroll_maker_checker_and_ledger_postings(): void
    {
        $this->investment(5_000_000_00); // Main Ops 4,750,000
        $chika = $this->employee('Chika Eze', 200_000_00);
        $service = app(PayrollService::class);
        $service->addAdjustment($chika, '2026-10', 'deduction', 'PAYE', 20_000_00, $this->hr);
        $run = $service->prepare('2026-10', $this->hr);

        try {
            $service->approve($run, $this->hr);
            $this->fail('Preparer approved own payroll');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }
        try {
            $service->pay($run, 'NIP', $this->accounts);
            $this->fail('Paid before approval');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }

        $service->approve($run, $this->admin);
        $cashBefore = $this->ledger->balance(LedgerAccount::MAIN_OPS);
        $service->pay($run, 'NIP-SAL', $this->accounts);

        $this->assertSame($cashBefore - 180_000_00, $this->ledger->balance(LedgerAccount::MAIN_OPS));            // net paid out
        $this->assertSame(200_000_00, $this->ledger->balance(LedgerAccount::PAYROLL_EXPENSE));                  // gross expense
        $this->assertSame(20_000_00, $this->ledger->balance(LedgerAccount::STATUTORY_PAYABLE));                 // withheld
        $this->assertSame(PayrollRun::PAID, $run->fresh()->status);
    }

    public function test_adjustments_are_locked_once_payroll_is_approved(): void
    {
        $chika = $this->employee();
        $service = app(PayrollService::class);
        $run = $service->prepare('2026-10', $this->hr);
        $service->approve($run, $this->admin);

        $this->expectException(BusinessRuleException::class);
        $service->addAdjustment($chika, '2026-10', 'allowance', 'Late', 1_000_00, $this->hr);
    }

    public function test_payroll_cannot_be_paid_without_cash(): void
    {
        $this->employee('Chika Eze', 200_000_00); // no investor money in the system at all
        $service = app(PayrollService::class);
        $run = $service->prepare('2026-10', $this->hr);
        $service->approve($run, $this->admin);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Insufficient funds');
        $service->pay($run, 'NIP', $this->accounts);
    }

    public function test_payroll_roles_are_separated_on_the_web(): void
    {
        $this->employee();
        $this->actingAs($this->hr)->post(route('admin.hr.payroll.prepare'), ['period' => '2026-10'])->assertSessionHasNoErrors();
        $run = PayrollRun::firstOrFail();

        $this->actingAs($this->hr)->post(route('admin.hr.payroll.approve', $run))->assertForbidden();
        $this->actingAs($this->accounts)->post(route('admin.hr.payroll.prepare'), ['period' => '2026-11'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.hr.payroll.approve', $run))->assertSessionHasNoErrors();
        $this->actingAs($this->hr)->post(route('admin.hr.payroll.pay', $run), ['reference' => 'X'])->assertForbidden();

        $this->actingAs($this->accounts)->get(route('admin.hr.payroll.show', $run))->assertOk()->assertSee('Net to pay staff');
        $this->actingAs($this->accounts)->get(route('admin.hr.payroll.export', $run))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->actingAs($this->sales)->get(route('admin.hr.payroll'))->assertForbidden();
        $this->actingAs($this->sales)->get(route('admin.hr.employees'))->assertForbidden();
        $this->actingAs($this->hr)->get(route('admin.hr.employees'))->assertOk();
    }

    // ---- reports ----

    public function test_the_trial_balance_balances_and_exports(): void
    {
        $this->investment(2_000_000_00);
        $this->employee();
        $payroll = app(PayrollService::class);
        $run = $payroll->prepare('2026-10', $this->hr);
        $payroll->approve($run, $this->admin);
        $payroll->pay($run, 'NIP', $this->accounts);

        $report = app(ReportService::class)->trialBalance(now());
        $this->assertTrue($report['balanced']);
        $this->assertSame($report['total_debit'], $report['total_credit']);

        $this->actingAs($this->auditor)->get(route('admin.reports.trial-balance'))->assertOk()->assertSee('the ledger balances');
        $csv = $this->actingAs($this->auditor)->get(route('admin.reports.trial-balance', ['format' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('MAIN_OPS', $csv->streamedContent());
    }

    public function test_the_trial_balance_respects_the_as_of_date(): void
    {
        $this->investment(1_000_000_00);
        $reports = app(ReportService::class);

        $before = $reports->trialBalance(Carbon::parse('2026-10-01'));
        $this->assertSame(0, $before['total_debit']);
        $this->assertSame(1_000_000_00 + 50_000_00 * 0, collect($reports->trialBalance(now())['rows'])->firstWhere('code', 'INVESTOR_FUNDS')['credit']);
    }

    public function test_profit_and_loss_shows_payroll_and_takaful_expenses(): void
    {
        $this->investment(5_000_000_00);
        $this->employee('Chika Eze', 200_000_00);
        $payroll = app(PayrollService::class);
        $run = $payroll->prepare('2026-10', $this->hr);
        $payroll->approve($run, $this->admin);
        $payroll->pay($run, 'NIP', $this->accounts);
        $policy = app(TakafulService::class)->create([
            'provider' => 'Noor', 'policy_number' => 'TK-9', 'description' => 'x', 'premium' => 25_000_00, 'cover_amount' => 1_000_000_00,
            'start_date' => '2026-10-01', 'end_date' => '2027-09-30',
        ], $this->accounts);
        app(TakafulService::class)->payPremium($policy, 'NIP', $this->accounts);

        $pl = app(ReportService::class)->profitAndLoss(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertSame(225_000_00, $pl['total_expenses']);   // 200,000 payroll + 25,000 takaful
        $this->assertSame(-225_000_00, $pl['net']);

        $this->actingAs($this->accounts)->get(route('admin.reports.profit-loss', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertSee('Payroll Expense');
    }

    public function test_investor_and_cycle_reports_export_csv(): void
    {
        $investment = $this->investment(1_000_000_00, 'Ada Obi');
        $cycle = app(CycleService::class)->open('Sesame Nov', Commodity::where('name', 'Sesame Seeds')->first(), null, $this->sales);

        $investors = $this->actingAs($this->accounts)->get(route('admin.reports.investors', ['format' => 'csv']))->streamedContent();
        $this->assertStringContainsString($investment->reference, $investors);
        $this->assertStringContainsString('Ada Obi', $investors);

        $cycles = $this->actingAs($this->accounts)->get(route('admin.reports.cycles', ['format' => 'csv']))->streamedContent();
        $this->assertStringContainsString($cycle->reference, $cycles);

        $this->actingAs($this->accounts)->get(route('admin.reports.distributions'))->assertOk();
        $this->actingAs($this->accounts)->get(route('admin.reports.index'))->assertOk();
    }

    public function test_reports_are_limited_to_report_viewers(): void
    {
        $this->actingAs($this->sales)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($this->hr)->get(route('admin.reports.trial-balance'))->assertForbidden();
        $this->actingAs($this->investment()->user)->get(route('admin.reports.investors'))->assertForbidden();
    }

    public function test_csv_cells_cannot_inject_formulas(): void
    {
        $this->assertSame("'=1+1", Csv::safe('=1+1'));
        $this->assertSame("'@SUM(A1)", Csv::safe('@SUM(A1)'));
        $this->assertSame('-5.00', Csv::safe('-5.00'));   // negative numbers are numbers, not formulas
        $this->assertSame('Normal text', Csv::safe('Normal text'));
    }

    // ---- audit trail ----

    public function test_the_audit_trail_is_visible_to_auditors_and_filterable(): void
    {
        $investment = $this->investment();
        activity()->performedOn($investment)->causedBy($this->accounts)->log('Hand-made audit entry');

        $this->actingAs($this->auditor)->get(route('admin.audit'))->assertOk()->assertSee('Hand-made audit entry')->assertSee('Payment confirmed');
        $this->actingAs($this->auditor)->get(route('admin.audit', ['q' => 'Hand-made']))->assertOk()->assertSee('Hand-made audit entry')->assertDontSee('Payment confirmed');
        $this->actingAs($this->auditor)->get(route('admin.audit', ['who' => $this->accounts->email]))->assertOk()->assertSee('Hand-made audit entry');

        $this->actingAs($this->sales)->get(route('admin.audit'))->assertForbidden();
        $this->actingAs($investment->user)->get(route('admin.audit'))->assertForbidden();
    }

    // ---- staff accounts ----

    public function test_a_director_can_create_a_staff_account_that_sets_its_own_password(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->post(route('admin.users.store'), ['name' => 'New Accountant', 'email' => 'new@afisab.test', 'role' => 'accounts'])->assertSessionHasNoErrors();

        $user = User::where('email', 'new@afisab.test')->firstOrFail();
        $this->assertTrue($user->hasRole('accounts'));
        $this->assertNotNull($user->email_verified_at);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_staff_accounts_cannot_be_created_with_the_investor_role_or_by_other_staff(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@afisab.test', 'role' => 'investor'])->assertSessionHasErrors('role');
        $this->actingAs($this->accounts)->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@afisab.test', 'role' => 'accounts'])->assertForbidden();
        $this->actingAs($this->hr)->get(route('admin.users'))->assertForbidden();
    }

    public function test_roles_can_change_but_not_your_own_and_not_the_last_super_admin(): void
    {
        $this->actingAs($this->admin)->put(route('admin.users.role', $this->sales), ['role' => 'procurement'])->assertSessionHasNoErrors();
        $this->assertTrue($this->sales->fresh()->hasRole('procurement'));

        $this->actingAs($this->admin)->put(route('admin.users.role', $this->admin), ['role' => 'accounts'])->assertSessionHasErrors('user');
        $this->assertTrue($this->admin->fresh()->hasRole('super-admin'));

        $second = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($second)->put(route('admin.users.role', $this->admin), ['role' => 'accounts'])->assertSessionHasNoErrors(); // fine: another super-admin remains
        $this->actingAs($this->admin->fresh())->get(route('admin.users'))->assertForbidden(); // demoted
        $this->actingAs($second)->put(route('admin.users.role', $this->accounts), ['role' => 'super-admin'])->assertSessionHasNoErrors();
    }

    public function test_the_last_super_admin_cannot_be_disabled(): void
    {
        $other = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($this->admin)->post(route('admin.users.toggle', $other))->assertSessionHasNoErrors(); // allowed: self remains
        $this->actingAs($this->admin)->post(route('admin.users.toggle', $this->admin))->assertSessionHasErrors('user'); // cannot disable yourself
        $this->assertNotNull($other->fresh()->disabled_at);
    }

    public function test_a_disabled_account_cannot_sign_in_and_loses_an_open_session(): void
    {
        $staff = User::factory()->create(['password' => 'Str0ngPassword1'])->assignRole('accounts');

        $this->actingAs($this->admin)->post(route('admin.users.toggle', $staff))->assertSessionHasNoErrors();
        $this->assertNotNull($staff->fresh()->disabled_at);

        // existing session is cut off
        $this->actingAs($staff->fresh())->get(route('admin.funds'))->assertRedirect(route('login'));

        // and a fresh login is refused even with the right password
        auth()->logout();
        $this->post('/login', ['email' => $staff->email, 'password' => 'Str0ngPassword1'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // re-enabling restores access
        $this->actingAs($this->admin)->post(route('admin.users.toggle', $staff))->assertSessionHasNoErrors();
        auth()->logout();
        $this->post('/login', ['email' => $staff->email, 'password' => 'Str0ngPassword1']);
        $this->assertAuthenticatedAs($staff->fresh());
    }

    // ---- work queue ----

    public function test_the_dashboard_lists_only_what_is_waiting_for_that_person(): void
    {
        $investment = $this->investment();
        $cycle = app(CycleService::class)->open('A', Commodity::first(), null, $this->sales);
        app(CycleService::class)->allocate($cycle, $investment, 500_000_00, $this->sales);
        $purchase = app(PurchaseService::class)->propose($cycle, $this->supplier(), 1_000, 5_000, $this->procurement);

        $this->actingAs($this->accounts)->get(route('admin.dashboard'))->assertOk()->assertSee('Purchases to approve');
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Purchases to approve');
        $this->actingAs($this->hr)->get(route('admin.dashboard'))->assertOk()->assertSee('Nothing is waiting for you')->assertDontSee('Purchases to approve');

        app(PurchaseService::class)->approve($purchase, $this->admin);
        $this->actingAs($this->accounts)->get(route('admin.dashboard'))->assertSee('Approved purchases to pay')->assertDontSee('Purchases to approve');
    }
}
