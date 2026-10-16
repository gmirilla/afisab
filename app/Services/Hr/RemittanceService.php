<?php

namespace App\Services\Hr;

use App\Exceptions\BusinessRuleException;
use App\Models\LedgerAccount;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\StatutoryRemittance;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Treasury\TreasuryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What is owed to the tax authority, the pension administrators and FMBN once a payroll is paid.
 *
 * createFor() runs inside PayrollService::pay(): one remittance per state (PAYE), per pension administrator
 * (employee + employer share) and one for NHF, each with a due date taken from the run's rule set.
 * Accounts then pays them: Dr the matching payable account / Cr Main Operations, after the cash guard, by someone
 * other than the person who prepared the payroll.
 */
class RemittanceService
{
    private const UNSPECIFIED_STATE = 'Unspecified state';

    public function __construct(private LedgerService $ledger, private TreasuryService $treasury) {}

    public function createFor(PayrollRun $run): void
    {
        if (! $run->hasStatutory() || $run->remittances()->exists()) {
            return;
        }
        $rules = $run->ruleSet->rules['remit'];
        $lines = $run->lines()->with(['employee', 'administrator'])->get();
        $paidAt = $run->paid_at ?? now();
        $nextMonth = Carbon::createFromFormat('Y-m-d', $run->period.'-01')->addMonthNoOverflow();

        $paye = $lines->where('paye', '>', 0)->groupBy(fn (PayrollLine $l) => $l->employee->tax_state ?: self::UNSPECIFIED_STATE);
        foreach ($paye as $state => $group) {
            $this->make($run, StatutoryRemittance::PAYE, $state, null, (int) $group->sum('paye'), $this->dayOf($nextMonth, $rules['paye_day']));
        }

        $pension = $lines->filter(fn (PayrollLine $l) => $l->pension_employee + $l->pension_employer > 0)->groupBy('pension_administrator_id');
        foreach ($pension as $administratorId => $group) {
            $this->make(
                $run, StatutoryRemittance::PENSION, $group->first()->administrator?->name ?? 'Unassigned pension administrator',
                $administratorId ?: null, (int) $group->sum(fn (PayrollLine $l) => $l->pension_employee + $l->pension_employer),
                $this->addWorkingDays($paidAt, $rules['pension_working_days']),
            );
        }

        if (($nhf = (int) $lines->sum('nhf')) > 0) {
            $this->make($run, StatutoryRemittance::NHF, 'FMBN (National Housing Fund)', null, $nhf, $this->dayOf($nextMonth, $rules['nhf_day']));
        }
    }

    public function remit(StatutoryRemittance $remittance, string $reference, User $by): StatutoryRemittance
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new BusinessRuleException('A payment reference is required.');
        }

        return DB::transaction(function () use ($remittance, $reference, $by) {
            $remittance = StatutoryRemittance::whereKey($remittance->id)->lockForUpdate()->with('run')->firstOrFail();

            if ($remittance->status !== StatutoryRemittance::DUE) {
                throw new BusinessRuleException('This remittance has already been paid.');
            }
            if ($remittance->run->prepared_by === $by->id) {
                throw new BusinessRuleException('The person who prepared the payroll cannot also remit its deductions.');
            }
            $this->treasury->assertCanPay($remittance->amount);

            $payable = match ($remittance->kind) {
                StatutoryRemittance::PAYE => LedgerAccount::PAYE_PAYABLE,
                StatutoryRemittance::PENSION => LedgerAccount::PENSION_PAYABLE,
                default => LedgerAccount::NHF_PAYABLE,
            };
            $this->ledger->transfer(
                LedgerAccount::MAIN_OPS, $payable, $remittance->amount,
                "{$remittance->label()} remitted: {$remittance->payee} ({$remittance->run->period})", $remittance, "REMIT-{$remittance->id}", $by->id,
            );

            $remittance->update(['status' => StatutoryRemittance::REMITTED, 'remitted_by' => $by->id, 'remitted_at' => now(), 'reference' => $reference]);
            activity()->performedOn($remittance)->causedBy($by)->withProperties(['reference' => $reference, 'amount' => $remittance->amount])
                ->log("{$remittance->label()} remitted to {$remittance->payee}");

            return $remittance;
        });
    }

    /** @return array<int, array<int, string>> the schedule to send with the payment */
    public function scheduleRows(StatutoryRemittance $remittance): array
    {
        $lines = $remittance->run->lines()->with('employee')->get();
        $naira = fn (int $kobo) => number_format($kobo / 100, 2, '.', '');

        return match ($remittance->kind) {
            StatutoryRemittance::PAYE => array_merge(
                [['Employee', 'Tax ID', 'State', 'Taxable pay this month (NGN)', 'PAYE (NGN)']],
                $lines->filter(fn (PayrollLine $l) => $l->paye > 0 && ($l->employee->tax_state ?: self::UNSPECIFIED_STATE) === $remittance->payee)
                    ->map(fn (PayrollLine $l) => [$l->employee->name, (string) $l->employee->tax_id, (string) $l->employee->tax_state, $naira($l->taxable), $naira($l->paye)])->values()->all(),
            ),
            StatutoryRemittance::PENSION => array_merge(
                [['Employee', 'RSA PIN', 'Pensionable pay (NGN)', 'Employee share (NGN)', 'Employer share (NGN)', 'Total (NGN)']],
                $lines->filter(fn (PayrollLine $l) => (int) $l->pension_administrator_id === (int) $remittance->pension_administrator_id && $l->pension_employee + $l->pension_employer > 0)
                    ->map(fn (PayrollLine $l) => [$l->employee->name, (string) $l->employee->rsa_pin, $naira($l->pensionable), $naira($l->pension_employee), $naira($l->pension_employer), $naira($l->pension_employee + $l->pension_employer)])->values()->all(),
            ),
            default => array_merge(
                [['Employee', 'NHF number', 'Basic pay (NGN)', 'Contribution (NGN)']],
                $lines->filter(fn (PayrollLine $l) => $l->nhf > 0)
                    ->map(fn (PayrollLine $l) => [$l->employee->name, (string) $l->employee->nhf_number, $naira($l->basic), $naira($l->nhf)])->values()->all(),
            ),
        };
    }

    private function make(PayrollRun $run, string $kind, string $payee, ?int $administratorId, int $amount, Carbon $due): void
    {
        if ($amount <= 0) {
            return;
        }
        $run->remittances()->create([
            'kind' => $kind, 'payee' => $payee, 'pension_administrator_id' => $administratorId,
            'amount' => $amount, 'due_date' => $due, 'status' => StatutoryRemittance::DUE,
        ]);
    }

    private function dayOf(Carbon $month, int $day): Carbon
    {
        return $month->copy()->day(min($day, $month->daysInMonth));
    }

    private function addWorkingDays(Carbon $from, int $days): Carbon
    {
        $date = $from->copy()->startOfDay();
        while ($days > 0) {
            $date->addDay();
            if (! $date->isWeekend()) {
                $days--;
            }
        }

        return $date;
    }
}
