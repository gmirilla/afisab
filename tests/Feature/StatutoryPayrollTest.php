<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\LedgerException;
use App\Models\Employee;
use App\Models\LedgerAccount;
use App\Models\PayrollRun;
use App\Models\PensionAdministrator;
use App\Models\StatutoryRemittance;
use App\Models\StatutoryRuleSet;
use App\Models\User;
use App\Services\Hr\PayrollService;
use App\Services\Hr\RemittanceService;
use App\Services\Hr\StatutoryCalculator;
use App\Services\Ledger\LedgerService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StatutoryPayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private User $director;

    private User $accounts;

    private PayrollService $payroll;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->hr = User::factory()->create()->assignRole('hr');
        $this->director = User::factory()->create()->assignRole('super-admin');
        $this->accounts = User::factory()->create()->assignRole('accounts');
        $this->payroll = app(PayrollService::class);
        $this->ledger = app(LedgerService::class);
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    }

    /** Worked by hand: 0% on the first 800,000, 15% on the next 2,200,000, 25% on the rest. Pension 8/10, NHF 2.5. */
    private function rules(): array
    {
        return [
            'paye' => ['bands' => [['width' => 80_000_000, 'rate_bps' => 0], ['width' => 220_000_000, 'rate_bps' => 1500], ['width' => null, 'rate_bps' => 2500]],
                'rent_relief_bps' => 2000, 'rent_relief_cap' => 50_000_000],
            'pension' => ['employee_bps' => 800, 'employer_bps' => 1000],
            'nhf' => ['employee_bps' => 250],
            'remit' => ['paye_day' => 10, 'nhf_day' => 30, 'pension_working_days' => 7],
        ];
    }

    private function activeSet(string $from = '2026-01-01'): StatutoryRuleSet
    {
        return StatutoryRuleSet::create(['name' => 'Test rates', 'effective_from' => $from, 'status' => StatutoryRuleSet::ACTIVE, 'rules' => $this->rules()]);
    }

    private function pfa(string $name): PensionAdministrator
    {
        return PensionAdministrator::create(['name' => $name, 'bank_name' => 'PFA Bank', 'account_number' => '1234567890', 'account_name' => $name]);
    }

    private function employee(string $name, int $basic, array $extra = []): Employee
    {
        return Employee::create($extra + ['name' => $name, 'basic_salary' => $basic, 'is_active' => true, 'bank_name' => 'Test Bank', 'account_number' => '0123456789', 'account_name' => $name]);
    }

    /** Ada: gross 450,000 (basic 300k + housing 100k + transport 50k), Lagos. Bayo: basic 200,000, Ogun. */
    private function staff(): array
    {
        $a = $this->employee('Ada Obi', 30_000_000, ['housing_allowance' => 10_000_000, 'transport_allowance' => 5_000_000, 'tax_state' => 'Lagos', 'pension_administrator_id' => $this->pfa('Alpha Pensions')->id]);
        $b = $this->employee('Bayo Ade', 20_000_000, ['tax_state' => 'Ogun', 'pension_administrator_id' => $this->pfa('Beta Pensions')->id]);

        return [$a, $b];
    }

    private function fundCash(): void
    {
        $this->ledger->transfer(LedgerAccount::INVENTORY, LedgerAccount::MAIN_OPS, 500_000_000_00, 'Simulated proceeds');
    }

    private function payThrough(string $period): PayrollRun
    {
        $run = $this->payroll->prepare($period, $this->hr);
        $this->payroll->approve($run, $this->director);
        $this->payroll->pay($run, 'NIP-'.$period, $this->accounts);

        return $run->fresh();
    }

    // ---- the maths ----

    public function test_first_month_matches_a_hand_worked_example(): void
    {
        $r = (new StatutoryCalculator)->calculate($this->rules(), [
            'basic' => 30_000_000, 'housing' => 10_000_000, 'transport' => 5_000_000, 'allowances' => 0, 'commission' => 0,
            'pension_exempt' => false, 'nhf_applies' => true, 'paye_exempt' => false, 'annual_rent' => 0, 'months' => 1,
            'ytd' => ['gross' => 0, 'pension' => 0, 'nhf' => 0, 'paye' => 0],
        ]);

        $this->assertSame(45_000_000, $r['gross']);
        $this->assertSame(3_600_000, $r['pension_employee']);   // 8% of 450,000
        $this->assertSame(4_500_000, $r['pension_employer']);   // 10%
        $this->assertSame(750_000, $r['nhf']);                  // 2.5% of basic 300,000
        $this->assertSame(40_650_000, $r['taxable']);           // 450,000 - 36,000 - 7,500 = 406,500
        $this->assertSame(6_662_500, $r['paye']);               // 66,625.00 by the monthly-scaled bands
    }

    public function test_steady_pay_gives_the_same_paye_every_month(): void
    {
        $calc = new StatutoryCalculator;
        $in = ['basic' => 30_000_000, 'housing' => 10_000_000, 'transport' => 5_000_000, 'allowances' => 0, 'commission' => 0, 'pension_exempt' => false, 'nhf_applies' => true, 'paye_exempt' => false, 'annual_rent' => 0];
        $ytd = ['gross' => 0, 'pension' => 0, 'nhf' => 0, 'paye' => 0];

        for ($m = 1; $m <= 12; $m++) {
            $r = $calc->calculate($this->rules(), $in + ['months' => $m, 'ytd' => $ytd]);
            $this->assertEqualsWithDelta(6_662_500, $r['paye'], 12, "month $m"); // equal up to kobo rounding of the scaled bands
            $ytd = ['gross' => $ytd['gross'] + $r['gross'], 'pension' => $ytd['pension'] + $r['pension_employee'], 'nhf' => $ytd['nhf'] + $r['nhf'], 'paye' => $ytd['paye'] + $r['paye']];
        }
    }

    public function test_a_commission_month_is_taxed_more_and_the_year_total_stays_right(): void
    {
        $calc = new StatutoryCalculator;
        $base = ['basic' => 30_000_000, 'housing' => 10_000_000, 'transport' => 5_000_000, 'allowances' => 0, 'pension_exempt' => false, 'nhf_applies' => true, 'paye_exempt' => false, 'annual_rent' => 0];
        $m1 = $calc->calculate($this->rules(), $base + ['commission' => 0, 'months' => 1, 'ytd' => ['gross' => 0, 'pension' => 0, 'nhf' => 0, 'paye' => 0]]);
        $m2 = $calc->calculate($this->rules(), $base + ['commission' => 20_000_000, 'months' => 2, 'ytd' => ['gross' => $m1['gross'], 'pension' => $m1['pension_employee'], 'nhf' => $m1['nhf'], 'paye' => $m1['paye']]]);

        $this->assertSame(11_662_500, $m2['paye']); // tax to date 183,250.00 less 66,625.00 already withheld
        $this->assertSame(18_325_000, $m1['paye'] + $m2['paye']);
    }

    public function test_rent_relief_reduces_paye_and_is_capped(): void
    {
        $calc = new StatutoryCalculator;
        $in = ['basic' => 30_000_000, 'housing' => 10_000_000, 'transport' => 5_000_000, 'allowances' => 0, 'commission' => 0, 'pension_exempt' => false, 'nhf_applies' => true, 'paye_exempt' => false, 'months' => 1, 'ytd' => ['gross' => 0, 'pension' => 0, 'nhf' => 0, 'paye' => 0]];

        $this->assertSame(5_829_167, $calc->calculate($this->rules(), $in + ['annual_rent' => 200_000_000])['paye']); // 20% of 2m = 400k relief
        $capped = $calc->calculate($this->rules(), $in + ['annual_rent' => 900_000_000])['paye'];                       // 20% of 9m = 1.8m, capped at 500k
        $atCap = $calc->calculate($this->rules(), $in + ['annual_rent' => 250_000_000])['paye'];                        // 20% of 2.5m = exactly the cap
        $this->assertSame($atCap, $capped);
    }

    public function test_exemptions_and_low_earners(): void
    {
        $calc = new StatutoryCalculator;
        $in = ['basic' => 30_000_000, 'housing' => 0, 'transport' => 0, 'allowances' => 0, 'commission' => 0, 'annual_rent' => 0, 'months' => 1, 'ytd' => ['gross' => 0, 'pension' => 0, 'nhf' => 0, 'paye' => 0]];

        $exempt = $calc->calculate($this->rules(), $in + ['pension_exempt' => true, 'nhf_applies' => false, 'paye_exempt' => true]);
        $this->assertSame([0, 0, 0, 0], [$exempt['pension_employee'], $exempt['pension_employer'], $exempt['nhf'], $exempt['paye']]);

        $low = $calc->calculate($this->rules(), ['basic' => 5_000_000] + $in + ['pension_exempt' => false, 'nhf_applies' => true, 'paye_exempt' => false]); // 50,000 a month is inside the tax-free band
        $this->assertSame(0, $low['paye']);
    }

    public function test_unsafe_rule_sets_are_refused(): void
    {
        $bad = fn (callable $change) => (function () use ($change) {
            $rules = $this->rules();
            $change($rules);
            StatutoryCalculator::assertValid($rules);
        });

        foreach ([
            'last band with a limit' => function (&$r) { $r['paye']['bands'][2]['width'] = 100; },
            'rate over 100%' => function (&$r) { $r['paye']['bands'][1]['rate_bps'] = 10_001; },
            'no bands' => function (&$r) { $r['paye']['bands'] = []; },
            'silly pension' => function (&$r) { $r['pension']['employee_bps'] = 9_000; },
            'bad due day' => function (&$r) { $r['remit']['paye_day'] = 40; },
        ] as $label => $change) {
            try {
                $bad($change)();
                $this->fail("$label was accepted");
            } catch (BusinessRuleException) {
                $this->addToAssertionCount(1);
            }
        }
        StatutoryCalculator::assertValid($this->rules());
    }

    // ---- payroll run ----

    public function test_payroll_applies_the_active_rule_set_and_totals_add_up(): void
    {
        $this->activeSet();
        $this->staff();

        $run = $this->payroll->prepare('2026-10', $this->hr);

        $this->assertSame(65_000_000, $run->total_gross);
        $this->assertSame(8_347_500, $run->total_paye);            // 66,625 + 16,850
        $this->assertSame(5_200_000, $run->total_pension_employee); // 36,000 + 16,000
        $this->assertSame(6_500_000, $run->total_pension_employer);
        $this->assertSame(1_250_000, $run->total_nhf);
        $this->assertSame(50_202_500, $run->total_net);              // 339,875 + 162,150
        $this->assertSame($run->total_gross - $run->total_paye - $run->total_pension_employee - $run->total_nhf, $run->total_net);
        $this->assertNotNull($run->statutory_rule_set_id);

        $ada = $run->lines()->whereHas('employee', fn ($q) => $q->where('name', 'Ada Obi'))->firstOrFail();
        $this->assertSame([45_000_000, 6_662_500, 33_987_500, 1], [$ada->gross(), $ada->paye, $ada->net, (int) $ada->tax_months]);
    }

    public function test_without_an_active_rule_set_nothing_statutory_is_calculated(): void
    {
        StatutoryRuleSet::create(['name' => 'Draft only', 'effective_from' => '2026-01-01', 'status' => StatutoryRuleSet::DRAFT, 'rules' => $this->rules()]);
        $this->activeSet('2026-11-01'); // starts next month
        $this->employee('Ada Obi', 30_000_000);

        $run = $this->payroll->prepare('2026-10', $this->hr);

        $this->assertSame([30_000_000, 0, 0, 0], [$run->total_net, $run->total_paye, $run->total_pension_employee, $run->total_nhf]);
        $this->assertNull($run->statutory_rule_set_id);
    }

    public function test_the_rule_set_in_force_is_chosen_by_effective_date(): void
    {
        $old = $this->activeSet('2026-01-01');
        $newRules = $this->rules();
        $newRules['pension']['employee_bps'] = 1000;
        $new = StatutoryRuleSet::create(['name' => 'From November', 'effective_from' => '2026-11-01', 'status' => StatutoryRuleSet::ACTIVE, 'rules' => $newRules]);

        $this->assertSame($old->id, StatutoryRuleSet::forPeriod('2026-10')->id);
        $this->assertSame($new->id, StatutoryRuleSet::forPeriod('2026-11')->id);
        $this->assertNull(StatutoryRuleSet::forPeriod('2025-12'));
    }

    public function test_staff_with_pension_need_an_administrator_unless_exempt(): void
    {
        $this->activeSet();
        $e = $this->employee('No Pfa', 20_000_000);

        try {
            $this->payroll->prepare('2026-10', $this->hr);
            $this->fail('prepared without an administrator');
        } catch (BusinessRuleException $ex) {
            $this->assertStringContainsString('no pension administrator', $ex->getMessage());
        }

        $e->update(['pension_exempt' => true]);
        $this->assertSame(0, $this->payroll->prepare('2026-10', $this->hr)->total_pension_employee);
    }

    public function test_year_to_date_paye_follows_varying_commission_across_months(): void
    {
        $this->activeSet();
        $this->fundCash();
        [$ada] = $this->staff();
        $this->payThrough('2026-10');

        $this->travelTo(Carbon::parse('2026-11-20 10:00'));
        $this->payroll->addAdjustment($ada, '2026-11', 'commission', 'Big quarter', 20_000_000, $this->hr);
        $nov = $this->payroll->prepare('2026-11', $this->hr);

        $line = $nov->lines()->where('employee_id', $ada->id)->firstOrFail();
        $this->assertSame(11_662_500, $line->paye);
        $this->assertSame(2, (int) $line->tax_months);
        $this->assertSame(65_000_000 - 0 + 20_000_000, $nov->total_gross);
    }

    public function test_an_earlier_draft_month_blocks_the_next_one(): void
    {
        $this->activeSet();
        $this->staff();
        $this->payroll->prepare('2026-10', $this->hr); // left as a draft

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('still a draft');
        $this->payroll->prepare('2026-11', $this->hr);
    }

    public function test_paying_posts_each_authority_to_its_own_payable_account_and_stays_balanced(): void
    {
        $this->activeSet();
        $this->fundCash();
        $this->staff();
        $cash = $this->ledger->balance(LedgerAccount::MAIN_OPS);

        $this->payThrough('2026-10');

        $this->assertSame($cash - 50_202_500, $this->ledger->balance(LedgerAccount::MAIN_OPS));
        $this->assertSame(65_000_000 + 6_500_000, $this->ledger->balance(LedgerAccount::PAYROLL_EXPENSE)); // gross + employer pension
        $this->assertSame(8_347_500, $this->ledger->balance(LedgerAccount::PAYE_PAYABLE));
        $this->assertSame(5_200_000 + 6_500_000, $this->ledger->balance(LedgerAccount::PENSION_PAYABLE));
        $this->assertSame(1_250_000, $this->ledger->balance(LedgerAccount::NHF_PAYABLE));
        $this->assertSame(0, $this->ledger->balance(LedgerAccount::STATUTORY_PAYABLE), 'only manual deductions go here');
        $this->assertSame(\App\Models\JournalLine::where('direction', 'debit')->sum('amount'), \App\Models\JournalLine::where('direction', 'credit')->sum('amount'));
    }

    // ---- remittances ----

    public function test_paying_creates_remittances_per_state_per_administrator_and_for_nhf(): void
    {
        $this->activeSet();
        $this->fundCash();
        $this->staff();
        $run = $this->payThrough('2026-10');

        $r = $run->remittances()->get();
        $this->assertCount(5, $r); // 2 states + 2 PFAs + NHF
        $this->assertSame(6_662_500, $r->where('kind', 'paye')->firstWhere('payee', 'Lagos')->amount);
        $this->assertSame(1_685_000, $r->where('kind', 'paye')->firstWhere('payee', 'Ogun')->amount);
        $this->assertSame(8_100_000, $r->where('kind', 'pension')->firstWhere('payee', 'Alpha Pensions')->amount); // 36,000 + 45,000
        $this->assertSame(3_600_000, $r->where('kind', 'pension')->firstWhere('payee', 'Beta Pensions')->amount);   // 16,000 + 20,000
        $this->assertSame(1_250_000, $r->firstWhere('kind', 'nhf')->amount);

        $this->assertSame('2026-11-10', $r->firstWhere('payee', 'Lagos')->due_date->toDateString());
        $this->assertSame('2026-11-30', $r->firstWhere('kind', 'nhf')->due_date->toDateString());
        $this->assertSame('2026-10-29', $r->firstWhere('payee', 'Alpha Pensions')->due_date->toDateString()); // 7 working days after Tue 20 Oct
        $this->assertSame(8_347_500 + 11_700_000 + 1_250_000, $r->sum('amount'));
    }

    public function test_remitting_posts_to_the_ledger_once_and_respects_the_rules(): void
    {
        $this->activeSet();
        $this->fundCash();
        $this->staff();
        $run = $this->payThrough('2026-10');
        $lagos = $run->remittances()->where('payee', 'Lagos')->firstOrFail();
        $service = app(RemittanceService::class);

        try {
            $service->remit($lagos, 'REF-1', $this->hr); // HR prepared the payroll
            $this->fail('preparer remitted');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }

        $cash = $this->ledger->balance(LedgerAccount::MAIN_OPS);
        $done = $service->remit($lagos, 'FIRS-123', $this->accounts);

        $this->assertSame(StatutoryRemittance::REMITTED, $done->status);
        $this->assertSame($cash - 6_662_500, $this->ledger->balance(LedgerAccount::MAIN_OPS));
        $this->assertSame(8_347_500 - 6_662_500, $this->ledger->balance(LedgerAccount::PAYE_PAYABLE));

        $this->expectException(BusinessRuleException::class);
        $service->remit($lagos, 'AGAIN', $this->accounts);
    }

    public function test_remittance_is_blocked_when_cash_is_short(): void
    {
        $this->activeSet();
        $this->ledger->transfer(LedgerAccount::INVENTORY, LedgerAccount::MAIN_OPS, 50_202_500, 'Exactly the net pay');
        $this->staff();
        $run = $this->payThrough('2026-10');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Insufficient funds');
        app(RemittanceService::class)->remit($run->remittances()->where('kind', 'nhf')->firstOrFail(), 'REF', $this->accounts);
    }

    public function test_schedules_list_the_right_people(): void
    {
        $this->activeSet();
        $this->fundCash();
        $this->staff();
        $run = $this->payThrough('2026-10');
        $service = app(RemittanceService::class);

        $paye = $service->scheduleRows($run->remittances()->where('payee', 'Lagos')->firstOrFail());
        $this->assertCount(2, $paye); // header + Ada only
        $this->assertSame(['Ada Obi', '', 'Lagos', '406500.00', '66625.00'], $paye[1]);

        $pension = $service->scheduleRows($run->remittances()->where('payee', 'Beta Pensions')->firstOrFail());
        $this->assertCount(2, $pension);
        $this->assertSame('Bayo Ade', $pension[1][0]);
        $this->assertSame(['200000.00', '16000.00', '20000.00', '36000.00'], array_slice($pension[1], 2));
    }

    // ---- rule-set governance (web) ----

    public function test_an_active_rule_set_is_immutable(): void
    {
        $set = $this->activeSet();

        $this->expectException(LedgerException::class);
        $set->update(['name' => 'Changed']);
    }

    private function form(array $over = []): array
    {
        return $over + [
            'name' => 'From 2027', 'effective_from' => '2027-01-01', 'note' => 'Confirmed with accountant',
            'bands' => [['width' => '800,000', 'rate' => '0'], ['width' => '2,200,000', 'rate' => '15'], ['width' => '', 'rate' => '25']],
            'rent_relief_pct' => '20', 'rent_relief_cap' => '500000',
            'pension_employee_pct' => '8', 'pension_employer_pct' => '10', 'nhf_pct' => '2.5',
            'paye_day' => 10, 'nhf_day' => 30, 'pension_working_days' => 7,
        ];
    }

    public function test_only_the_super_admin_edits_rates_and_activation_needs_a_password(): void
    {
        // HR and Directors-by-role can read; nobody but statutory.manage can write.
        $this->actingAs($this->hr)->get(route('admin.statutory'))->assertOk()->assertSee('PAYE, pension and NHF rates');
        $this->actingAs($this->hr)->post(route('admin.statutory.store'), $this->form())->assertForbidden();
        $this->actingAs($this->accounts)->get(route('admin.statutory'))->assertOk();
        $this->actingAs(User::factory()->create()->assignRole('investor'))->get(route('admin.statutory'))->assertForbidden();

        $this->actingAs($this->director)->post(route('admin.statutory.store'), $this->form())->assertSessionHasNoErrors();
        $set = StatutoryRuleSet::where('name', 'From 2027')->firstOrFail();
        $this->assertSame(StatutoryRuleSet::DRAFT, $set->status);
        $this->assertSame([['width' => 80_000_000, 'rate_bps' => 0], ['width' => 220_000_000, 'rate_bps' => 1500], ['width' => null, 'rate_bps' => 2500]], $set->rules['paye']['bands']);
        $this->assertSame(250, $set->rules['nhf']['employee_bps']);

        $this->actingAs($this->hr)->post(route('admin.statutory.activate', $set), ['password' => 'password'])->assertForbidden();
        $this->actingAs($this->director)->post(route('admin.statutory.activate', $set), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertSame(StatutoryRuleSet::DRAFT, $set->fresh()->status);

        $this->actingAs($this->director)->post(route('admin.statutory.activate', $set), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame(StatutoryRuleSet::ACTIVE, $set->fresh()->status);

        // Locked now: no edit, no delete; a copy is the way to change it.
        $this->actingAs($this->director)->put(route('admin.statutory.update', $set), $this->form(['name' => 'Sneaky']))->assertSessionHasErrors('statutory');
        $this->actingAs($this->director)->delete(route('admin.statutory.destroy', $set))->assertSessionHasErrors('statutory');
        $this->assertSame('From 2027', $set->fresh()->name);

        $this->actingAs($this->director)->post(route('admin.statutory.copy', $set))->assertSessionHasNoErrors();
        $this->assertSame(1, StatutoryRuleSet::where('status', StatutoryRuleSet::DRAFT)->where('name', 'From 2027 (copy)')->count());
        $this->assertDatabaseHas('activity_log', ['description' => 'Statutory rule set activated', 'causer_id' => $this->director->id]);
    }

    public function test_bad_rates_are_rejected_at_the_form(): void
    {
        $this->actingAs($this->director)->post(route('admin.statutory.store'), $this->form(['nhf_pct' => 'abc']))->assertSessionHasErrors('statutory');
        $this->actingAs($this->director)->post(route('admin.statutory.store'), $this->form(['bands' => [['width' => '800000', 'rate' => '0'], ['width' => '100', 'rate' => '15']]]))->assertSessionHasErrors('statutory'); // last band has a limit
        $this->actingAs($this->director)->post(route('admin.statutory.store'), $this->form(['pension_employee_pct' => '80']))->assertSessionHasErrors('statutory');
        $this->assertSame(0, StatutoryRuleSet::where('name', 'From 2027')->count());
    }

    public function test_the_seeded_suggestion_is_a_draft_and_changes_nothing(): void
    {
        $seeded = StatutoryRuleSet::firstOrFail();
        $this->assertSame(StatutoryRuleSet::DRAFT, $seeded->status);
        StatutoryCalculator::assertValid($seeded->rules);
        $this->assertNull(StatutoryRuleSet::forPeriod('2026-10'));
    }

    // ---- screens ----

    public function test_employee_and_administrator_screens_save_the_new_fields(): void
    {
        $pfa = $this->pfa('Alpha Pensions');
        $this->actingAs($this->hr)->post(route('admin.hr.pfas.save'), ['name' => 'Gamma Pensions', 'account_number' => '0987654321'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pension_administrators', ['name' => 'Gamma Pensions']);
        $this->actingAs($this->accounts)->post(route('admin.hr.pfas.save'), ['name' => 'Nope'])->assertForbidden();

        $this->actingAs($this->hr)->post(route('admin.hr.employees.save'), [
            'name' => 'Chika Eze', 'basic_salary' => '250,000', 'housing_allowance' => '50,000', 'annual_rent' => '1,200,000',
            'pension_administrator_id' => $pfa->id, 'rsa_pin' => 'PEN100200300', 'nhf_number' => 'NHF1', 'tax_state' => 'Lagos',
            'nhf_applies' => '0', 'pension_exempt' => '0', 'paye_exempt' => '0',
        ])->assertSessionHasNoErrors();

        $e = Employee::where('name', 'Chika Eze')->firstOrFail();
        $this->assertSame([25_000_000, 5_000_000, 0, 120_000_000], [$e->basic_salary, $e->housing_allowance, $e->transport_allowance, $e->annual_rent]);
        $this->assertSame([$pfa->id, 'Lagos', false], [$e->pension_administrator_id, $e->tax_state, $e->nhf_applies]);

        // An update that leaves money fields blank keeps them; a hidden 0 switches a flag off.
        $this->actingAs($this->hr)->post(route('admin.hr.employees.save', $e), ['name' => 'Chika Eze', 'basic_salary' => '250,000', 'housing_allowance' => '', 'nhf_applies' => '1', 'pension_exempt' => '1'])->assertSessionHasNoErrors();
        $e->refresh();
        $this->assertSame(5_000_000, $e->housing_allowance);
        $this->assertTrue($e->nhf_applies);
        $this->assertTrue($e->pension_exempt);

        foreach (['admin.hr.employees', 'admin.hr.pfas', 'admin.hr.remittances'] as $screen) {
            $this->actingAs($this->hr)->get(route($screen))->assertOk();
        }
    }

    public function test_remittance_screens_and_permissions(): void
    {
        $this->activeSet();
        $this->fundCash();
        $this->staff();
        $run = $this->payThrough('2026-10');
        $nhf = $run->remittances()->where('kind', 'nhf')->firstOrFail();

        $this->actingAs($this->accounts)->get(route('admin.hr.remittances'))->assertOk()->assertSee('Alpha Pensions')->assertSee('FMBN');
        $this->actingAs($this->accounts)->get(route('admin.hr.remittances.schedule', $nhf))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($this->director)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($this->accounts)->get(route('admin.dashboard'))->assertSee('remittances to pay');

        $this->actingAs($this->hr)->post(route('admin.hr.remittances.pay', $nhf), ['reference' => 'X'])->assertForbidden();
        $this->actingAs($this->accounts)->post(route('admin.hr.remittances.pay', $nhf), ['reference' => 'FMBN-1'])->assertSessionHasNoErrors();
        $this->actingAs($this->accounts)->post(route('admin.hr.remittances.pay', $nhf), ['reference' => 'FMBN-1'])->assertSessionHasErrors('action'); // double submit
        $this->assertSame(1, \App\Models\JournalEntry::where('reference', 'REMIT-'.$nhf->id)->count());

        $this->actingAs($this->hr)->get(route('admin.hr.payroll.show', $run))->assertOk()->assertSee('Test rates')->assertSee('Alpha Pensions');
    }
}
