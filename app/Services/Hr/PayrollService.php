<?php

namespace App\Services\Hr;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\LedgerAccount;
use App\Models\PayrollAdjustment;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\StatutoryRuleSet;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Treasury\TreasuryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly payroll: HR prepares, a Director approves (a different person), Accounts pays (not the preparer).
 *
 * Pension, NHF and PAYE are calculated from the active StatutoryRuleSet for the month (year-to-date PAYE; see
 * StatutoryCalculator). With no active rule set nothing statutory is calculated and HR enters deductions by hand,
 * exactly as before. Loans and other manual deductions are always entered by HR as adjustments.
 *
 * Paying posts Dr Payroll Expense (gross + employer pension) / Cr Main Operations (net) / Cr PAYE, Pension, NHF and
 * Payroll Deductions Payable, and creates the remittances Accounts must then pay to each authority.
 */
class PayrollService
{
    public function __construct(
        private LedgerService $ledger,
        private TreasuryService $treasury,
        private StatutoryCalculator $calculator,
        private RemittanceService $remittances,
    ) {}

    public function addAdjustment(Employee $employee, string $period, string $type, string $description, int $amount, User $by): PayrollAdjustment
    {
        $this->assertPeriod($period);
        if (! in_array($type, PayrollAdjustment::TYPES, true)) {
            throw new BusinessRuleException('Unknown adjustment type.');
        }
        if ($amount <= 0) {
            throw new BusinessRuleException('The amount must be greater than zero.');
        }
        $this->assertEditable($period);

        $adjustment = $employee->adjustments()->create([
            'period' => $period, 'type' => $type, 'description' => $description, 'amount' => $amount, 'created_by' => $by->id,
        ]);
        activity()->performedOn($employee)->causedBy($by)->withProperties(['period' => $period, 'type' => $type, 'amount' => $amount])->log('Payroll adjustment added');

        return $adjustment;
    }

    public function removeAdjustment(PayrollAdjustment $adjustment, User $by): void
    {
        $this->assertEditable($adjustment->period);
        activity()->performedOn($adjustment->employee)->causedBy($by)->withProperties(['period' => $adjustment->period, 'type' => $adjustment->type])->log('Payroll adjustment removed');
        $adjustment->delete();
    }

    /** Build (or rebuild, while still a draft) the run for a month from active employees and that month's adjustments. */
    public function prepare(string $period, User $by): PayrollRun
    {
        $this->assertPeriod($period);

        return DB::transaction(function () use ($period, $by) {
            $run = PayrollRun::where('period', $period)->lockForUpdate()->first();
            if ($run && $run->status !== PayrollRun::DRAFT) {
                throw new BusinessRuleException("The {$run->label()} payroll is already {$run->status} and can no longer be changed.");
            }

            $employees = Employee::where('is_active', true)->with(['adjustments' => fn ($q) => $q->where('period', $period)])->get();
            if ($employees->isEmpty()) {
                throw new BusinessRuleException('There are no active employees to pay.');
            }

            $ruleSet = StatutoryRuleSet::forPeriod($period);
            $history = $ruleSet ? $this->yearToDate($period, $employees->pluck('id')->all()) : collect();

            $run ??= PayrollRun::create(['period' => $period, 'status' => PayrollRun::DRAFT]);
            $run->lines()->delete();

            $t = ['gross' => 0, 'manual' => 0, 'net' => 0, 'paye' => 0, 'pe' => 0, 'er' => 0, 'nhf' => 0];
            foreach ($employees as $employee) {
                $allowances = (int) $employee->adjustments->where('type', 'allowance')->sum('amount');
                $commission = (int) $employee->adjustments->where('type', 'commission')->sum('amount');
                $manual = (int) $employee->adjustments->where('type', 'deduction')->sum('amount');

                $calc = [
                    'gross' => $employee->basic_salary + $employee->housing_allowance + $employee->transport_allowance + $allowances + $commission,
                    'pensionable' => 0, 'pension_employee' => 0, 'pension_employer' => 0, 'nhf' => 0, 'taxable' => 0, 'paye' => 0,
                ];
                $months = 0;
                if ($ruleSet) {
                    [$ytd, $months] = $this->yearToDateFor($history->get($employee->id, collect()), $period);
                    $calc = $this->calculator->calculate($ruleSet->rules, [
                        'basic' => $employee->basic_salary, 'housing' => $employee->housing_allowance, 'transport' => $employee->transport_allowance,
                        'allowances' => $allowances, 'commission' => $commission,
                        'pension_exempt' => $employee->pension_exempt, 'nhf_applies' => $employee->nhf_applies, 'paye_exempt' => $employee->paye_exempt,
                        'annual_rent' => $employee->annual_rent, 'months' => $months, 'ytd' => $ytd,
                    ]);
                    if ($calc['pension_employee'] + $calc['pension_employer'] > 0 && ! $employee->pension_administrator_id) {
                        throw new BusinessRuleException("{$employee->name} has no pension administrator. Assign one (or mark them pension-exempt) before preparing payroll.");
                    }
                }

                $statutory = $calc['pension_employee'] + $calc['nhf'] + $calc['paye'];
                if ($manual + $statutory > $calc['gross']) {
                    throw new BusinessRuleException("Deductions for {$employee->name} exceed their gross pay.");
                }
                $net = $calc['gross'] - $manual - $statutory;

                $run->lines()->create([
                    'employee_id' => $employee->id, 'basic' => $employee->basic_salary, 'housing' => $employee->housing_allowance,
                    'transport' => $employee->transport_allowance, 'allowances' => $allowances, 'commission' => $commission,
                    'deductions' => $manual, 'pensionable' => $calc['pensionable'], 'pension_employee' => $calc['pension_employee'],
                    'pension_employer' => $calc['pension_employer'], 'nhf' => $calc['nhf'], 'taxable' => $calc['taxable'], 'paye' => $calc['paye'],
                    'tax_months' => $months, 'pension_administrator_id' => $employee->pension_administrator_id, 'net' => $net,
                ]);
                $t['gross'] += $calc['gross'];
                $t['manual'] += $manual;
                $t['net'] += $net;
                $t['paye'] += $calc['paye'];
                $t['pe'] += $calc['pension_employee'];
                $t['er'] += $calc['pension_employer'];
                $t['nhf'] += $calc['nhf'];
            }

            $run->update([
                'total_gross' => $t['gross'], 'total_deductions' => $t['manual'], 'total_net' => $t['net'], 'prepared_by' => $by->id,
                'statutory_rule_set_id' => $ruleSet?->id, 'total_paye' => $t['paye'], 'total_pension_employee' => $t['pe'],
                'total_pension_employer' => $t['er'], 'total_nhf' => $t['nhf'],
            ]);
            activity()->performedOn($run)->causedBy($by)->log("Payroll {$period} prepared");

            return $run->refresh();
        });
    }

    public function approve(PayrollRun $run, User $by): PayrollRun
    {
        return DB::transaction(function () use ($run, $by) {
            $run = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($run->status !== PayrollRun::DRAFT) {
                throw new BusinessRuleException('Only a draft payroll can be approved.');
            }
            if ($run->prepared_by === $by->id) {
                throw new BusinessRuleException('Payroll must be approved by someone other than the person who prepared it.');
            }
            if ($run->lines()->doesntExist()) {
                throw new BusinessRuleException('This payroll has no lines.');
            }

            $run->update(['status' => PayrollRun::APPROVED, 'approved_by' => $by->id, 'approved_at' => now()]);
            activity()->performedOn($run)->causedBy($by)->log("Payroll {$run->period} approved");

            return $run;
        });
    }

    public function pay(PayrollRun $run, string $reference, User $by): PayrollRun
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new BusinessRuleException('A payment reference is required.');
        }

        return DB::transaction(function () use ($run, $reference, $by) {
            $run = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($run->status !== PayrollRun::APPROVED) {
                throw new BusinessRuleException('Only an approved payroll can be paid.');
            }
            if ($run->prepared_by === $by->id) {
                throw new BusinessRuleException('The person who prepared the payroll cannot also pay it.');
            }
            $this->treasury->assertCanPay($run->total_net);

            $lines = [
                ['account' => LedgerAccount::PAYROLL_EXPENSE, 'direction' => 'debit', 'amount' => $run->total_gross + $run->total_pension_employer],
                ['account' => LedgerAccount::MAIN_OPS, 'direction' => 'credit', 'amount' => $run->total_net],
            ];
            foreach ([
                LedgerAccount::STATUTORY_PAYABLE => $run->total_deductions,
                LedgerAccount::PAYE_PAYABLE => $run->total_paye,
                LedgerAccount::PENSION_PAYABLE => $run->total_pension_employee + $run->total_pension_employer,
                LedgerAccount::NHF_PAYABLE => $run->total_nhf,
            ] as $account => $amount) {
                if ($amount > 0) {
                    $lines[] = ['account' => $account, 'direction' => 'credit', 'amount' => $amount];
                }
            }
            $this->ledger->post("Payroll {$run->period}", $lines, "PAYROLL-{$run->period}", $run, $by->id);

            $run->update(['status' => PayrollRun::PAID, 'paid_by' => $by->id, 'paid_at' => now(), 'payment_reference' => $reference]);
            $this->remittances->createFor($run);
            activity()->performedOn($run)->causedBy($by)->withProperties(['reference' => $reference])->log("Payroll {$run->period} paid");

            return $run;
        });
    }

    /** @return array<int, array<int, string>> bank-upload rows */
    public function exportRows(PayrollRun $run): array
    {
        $rows = [['Employee', 'Bank', 'Account number', 'Account name', 'Net pay (NGN)', 'Narration']];
        foreach ($run->lines()->with('employee')->get() as $line) {
            $rows[] = [
                $line->employee->name, (string) $line->employee->bank_name, (string) $line->employee->account_number,
                (string) $line->employee->account_name, number_format($line->net / 100, 2, '.', ''), "AFISAB SALARY {$run->period}",
            ];
        }

        return $rows;
    }

    /**
     * Earlier months of the same tax year (calendar year) that are approved or paid, per employee.
     * Refuses to go on while an earlier month is still a draft: its figures would be missing from the year-to-date.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, PayrollLine>>
     */
    private function yearToDate(string $period, array $employeeIds)
    {
        $yearStart = substr($period, 0, 4).'-01';

        $draft = PayrollRun::where('period', '>=', $yearStart)->where('period', '<', $period)->where('status', PayrollRun::DRAFT)->orderBy('period')->first();
        if ($draft) {
            throw new BusinessRuleException("The {$draft->label()} payroll is still a draft. Approve it first, so PAYE for this month can use the year-to-date figures.");
        }

        return PayrollLine::with('run')->whereIn('employee_id', $employeeIds)
            ->whereHas('run', fn ($q) => $q->where('period', '>=', $yearStart)->where('period', '<', $period)->whereIn('status', [PayrollRun::APPROVED, PayrollRun::PAID]))
            ->get()->groupBy('employee_id');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PayrollLine>  $earlier
     * @return array{0: array{gross: int, pension: int, nhf: int, paye: int}, 1: int} year-to-date totals, and months of the tax year counted
     */
    private function yearToDateFor($earlier, string $period): array
    {
        $first = $earlier->isEmpty() ? $period : $earlier->map(fn (PayrollLine $l) => $l->run->period)->min();
        $months = (int) substr($period, 5, 2) - (int) substr($first, 5, 2) + 1;

        return [[
            'gross' => (int) $earlier->sum(fn (PayrollLine $l) => $l->gross()),
            'pension' => (int) $earlier->sum('pension_employee'),
            'nhf' => (int) $earlier->sum('nhf'),
            'paye' => (int) $earlier->sum('paye'),
        ], $months];
    }

    private function assertPeriod(string $period): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new BusinessRuleException('The period must look like 2026-10.');
        }
    }

    private function assertEditable(string $period): void
    {
        $run = PayrollRun::where('period', $period)->first();
        if ($run && $run->status !== PayrollRun::DRAFT) {
            throw new BusinessRuleException('The payroll for '.Carbon::createFromFormat('Y-m', $period)->format('F Y').' is already '.$run->status.'; adjustments are locked.');
        }
    }
}
