<?php

namespace App\Services\Hr;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\LedgerAccount;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Treasury\TreasuryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly payroll: HR prepares, a Director approves (a different person), Accounts pays (not the preparer).
 *
 * Deductions (tax, pension, loans) are entered by HR as adjustments; statutory amounts are NOT calculated
 * automatically. Paying posts Dr Payroll Expense (gross) / Cr Main Operations (net) / Cr Payroll Deductions Payable.
 */
class PayrollService
{
    public function __construct(private LedgerService $ledger, private TreasuryService $treasury) {}

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

            $run ??= PayrollRun::create(['period' => $period, 'status' => PayrollRun::DRAFT]);
            $run->lines()->delete();

            $gross = $deductions = $net = 0;
            foreach ($employees as $employee) {
                $allowances = (int) $employee->adjustments->where('type', 'allowance')->sum('amount');
                $commission = (int) $employee->adjustments->where('type', 'commission')->sum('amount');
                $deducted = (int) $employee->adjustments->where('type', 'deduction')->sum('amount');
                $lineGross = $employee->basic_salary + $allowances + $commission;

                if ($deducted > $lineGross) {
                    throw new BusinessRuleException("Deductions for {$employee->name} exceed their gross pay.");
                }

                $run->lines()->create([
                    'employee_id' => $employee->id, 'basic' => $employee->basic_salary, 'allowances' => $allowances,
                    'commission' => $commission, 'deductions' => $deducted, 'net' => $lineGross - $deducted,
                ]);
                $gross += $lineGross;
                $deductions += $deducted;
                $net += $lineGross - $deducted;
            }

            $run->update(['total_gross' => $gross, 'total_deductions' => $deductions, 'total_net' => $net, 'prepared_by' => $by->id]);
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
                ['account' => LedgerAccount::PAYROLL_EXPENSE, 'direction' => 'debit', 'amount' => $run->total_gross],
                ['account' => LedgerAccount::MAIN_OPS, 'direction' => 'credit', 'amount' => $run->total_net],
            ];
            if ($run->total_deductions > 0) {
                $lines[] = ['account' => LedgerAccount::STATUTORY_PAYABLE, 'direction' => 'credit', 'amount' => $run->total_deductions];
            }
            $this->ledger->post("Payroll {$run->period}", $lines, "PAYROLL-{$run->period}", $run, $by->id);

            $run->update(['status' => PayrollRun::PAID, 'paid_by' => $by->id, 'paid_at' => now(), 'payment_reference' => $reference]);
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
