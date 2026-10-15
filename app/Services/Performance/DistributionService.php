<?php

namespace App\Services\Performance;

use App\Exceptions\BusinessRuleException;
use App\Models\DistributionLine;
use App\Models\DistributionRun;
use App\Models\LedgerAccount;
use App\Models\PerformanceEntry;
use App\Models\User;
use App\Notifications\DistributionNotice;
use App\Notifications\ProfitPaid;
use App\Services\Ledger\LedgerService;
use App\Services\Treasury\TreasuryService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly profit distribution.
 *
 *  27th (end of day)  calculate: sweep all unassigned trading results up to the cut-off into a run,
 *                     work out each policy's monthly net profit and the 20% / 30% / 70% split
 *  27th - 29th        a different person approves (maker-checker); approval books the liability
 *  by month end       Accounts pays investors (bank transfer / CSV) and marks lines paid
 *
 * Results recorded after the cut-off simply roll into next month's run, so nothing is ever missed.
 */
class DistributionService
{
    public function __construct(private ProfitSplit $split, private LedgerService $ledger, private TreasuryService $treasury) {}

    /** Calculate (or recalculate, while not yet approved) the run for the month containing $month. */
    public function calculate(?CarbonInterface $month = null, ?User $by = null, bool $notify = true): DistributionRun
    {
        $month = Carbon::instance($month ?? now())->startOfMonth();
        $period = $month->format('Y-m');
        $cutoff = $month->copy()->day(min((int) config('afisab.distribution_cutoff_day'), $month->daysInMonth))->startOfDay();
        $payBy = $month->copy()->endOfMonth()->startOfDay();

        $run = DB::transaction(function () use ($period, $cutoff, $payBy, $by) {
            $run = DistributionRun::where('period', $period)->lockForUpdate()->first();

            if ($run && $run->status !== DistributionRun::CALCULATED) {
                throw new BusinessRuleException("The {$run->label()} run is already {$run->status} and can no longer be recalculated.");
            }

            if ($run) {
                PerformanceEntry::where('distribution_run_id', $run->id)->update(['distribution_run_id' => null]);
                $run->lines()->delete();
            } else {
                $run = DistributionRun::create(['period' => $period, 'cutoff_date' => $cutoff, 'pay_by' => $payBy, 'status' => DistributionRun::CALCULATED]);
            }

            $entries = PerformanceEntry::whereNull('distribution_run_id')
                ->where('recorded_at', '<=', $cutoff->copy()->endOfDay())
                ->get()->groupBy('investment_id');

            $totals = ['net' => 0, 'retained' => 0, 'investor' => 0, 'company' => 0];
            foreach ($entries as $investmentId => $group) {
                $net = (int) $group->sum('net_profit');
                $split = $this->split->split($net);

                $run->lines()->create([
                    'investment_id' => $investmentId,
                    'net_profit' => $net,
                    'retained' => $split['retained'],
                    'distributable' => $split['distributable'],
                    'investor_share' => $split['investor'],
                    'company_share' => $split['company'],
                    'status' => DistributionLine::PENDING,
                ]);
                PerformanceEntry::whereIn('id', $group->pluck('id'))->update(['distribution_run_id' => $run->id]);

                $totals['net'] += $net;
                $totals['retained'] += $split['retained'];
                $totals['investor'] += $split['investor'];
                $totals['company'] += $split['company'];
            }

            $run->update([
                'total_net_profit' => $totals['net'],
                'total_retained' => $totals['retained'],
                'total_investor' => $totals['investor'],
                'total_company' => $totals['company'],
                'calculated_by' => $by?->id,
                'calculated_at' => now(),
            ]);

            activity()->performedOn($run)->causedBy($by)->log("Distribution run {$period} calculated");

            return $run->refresh();
        });

        if ($notify) {
            $this->notify($this->approvers(), $run, "Profit distribution for {$run->label()} is ready for approval",
                'Investors must be paid by '.$run->pay_by->format('d F Y').'. Please review and approve the run.');
        }

        return $run;
    }

    /** Checker step: approve the run and book what is owed to investors. */
    public function approve(DistributionRun $run, User $by): DistributionRun
    {
        return DB::transaction(function () use ($run, $by) {
            $run = DistributionRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($run->status !== DistributionRun::CALCULATED) {
                throw new BusinessRuleException('Only a calculated run can be approved.');
            }
            if ($run->calculated_by !== null && $run->calculated_by === $by->id) {
                throw new BusinessRuleException('The run must be approved by someone other than the person who calculated it.');
            }

            foreach ($run->lines()->with('investment')->get() as $line) {
                if ($line->investor_share > 0) {
                    $this->ledger->post(
                        "Investor profit share {$run->period} [{$line->investment->reference}]",
                        [
                            ['account' => LedgerAccount::TRADING_INCOME, 'direction' => 'debit', 'amount' => $line->investor_share, 'subject' => $line->investment],
                            ['account' => LedgerAccount::INVESTOR_PROFIT_PAYABLE, 'direction' => 'credit', 'amount' => $line->investor_share, 'subject' => $line->investment],
                        ],
                        "DIST-{$run->period}-{$line->investment_id}-ACR",
                        $line->investment,
                        $by->id,
                    );
                    $line->update(['status' => DistributionLine::PAYABLE]);
                } else {
                    $line->update(['status' => DistributionLine::NONE]);
                }
            }

            $run->update(['status' => DistributionRun::APPROVED, 'approved_by' => $by->id, 'approved_at' => now()]);
            $this->closeIfFullyPaid($run);

            activity()->performedOn($run)->causedBy($by)->log("Distribution run {$run->period} approved");

            return $run->refresh();
        });
    }

    /** Record that one investor has been paid (outside the system, by bank transfer). */
    public function markLinePaid(DistributionLine $line, string $reference, User $by): DistributionLine
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new BusinessRuleException('A payment reference is required.');
        }

        $line = DB::transaction(function () use ($line, $reference, $by) {
            $line = DistributionLine::whereKey($line->id)->lockForUpdate()->with(['run', 'investment'])->firstOrFail();

            if ($line->status !== DistributionLine::PAYABLE) {
                throw new BusinessRuleException('This line is not awaiting payment.');
            }

            $this->treasury->assertCanPay($line->investor_share);

            $this->ledger->transfer(
                LedgerAccount::MAIN_OPS,
                LedgerAccount::INVESTOR_PROFIT_PAYABLE,
                $line->investor_share,
                "Investor profit paid {$line->run->period} [{$line->investment->reference}]",
                $line->investment,
                "DIST-{$line->run->period}-{$line->investment_id}-PAY",
            );

            $line->update(['status' => DistributionLine::PAID, 'paid_at' => now(), 'payment_reference' => $reference, 'paid_by' => $by->id]);
            $this->closeIfFullyPaid($line->run);
            activity()->performedOn($line->investment)->causedBy($by)->withProperties(['reference' => $reference])->log("Profit share {$line->run->period} paid");

            return $line;
        });

        $line->investment->user->notify(new ProfitPaid($line));

        return $line;
    }

    /** @return int number of lines paid */
    public function markAllPaid(DistributionRun $run, string $batchReference, User $by): int
    {
        if ($run->status !== DistributionRun::APPROVED) {
            throw new BusinessRuleException('Only an approved run can be paid.');
        }

        $count = 0;
        foreach ($run->lines()->where('status', DistributionLine::PAYABLE)->get() as $line) {
            $this->markLinePaid($line, $batchReference, $by);
            $count++;
        }

        return $count;
    }

    /**
     * Bank-upload rows for an approved run.
     *
     * @return array<int, array<int, string>>
     */
    public function exportRows(DistributionRun $run): array
    {
        $rows = [['Investment', 'Investor', 'Bank', 'Account number', 'Account name', 'Amount (NGN)', 'Narration', 'Status']];

        foreach ($run->lines()->with('investment.user.investorProfile')->where('investor_share', '>', 0)->get() as $line) {
            $profile = $line->investment->user->investorProfile;
            $rows[] = array_map($this->safeCell(...), [
                $line->investment->reference,
                $line->investment->user->name,
                (string) $profile?->bank_name,
                (string) $profile?->account_number,
                (string) $profile?->account_name,
                number_format($line->investor_share / 100, 2, '.', ''),
                "AFISAB PROFIT {$run->period} {$line->investment->reference}",
                $line->status,
            ]);
        }

        return $rows;
    }

    /** @return array{cutoff: Carbon, pay_by: Carbon} the next calculation date and payment deadline */
    public static function nextSchedule(?CarbonInterface $from = null): array
    {
        $today = Carbon::instance($from ?? now())->startOfDay();
        $day = (int) config('afisab.distribution_cutoff_day');

        $cutoff = $today->copy()->startOfMonth()->day(min($day, $today->daysInMonth));
        if ($today->gt($cutoff)) {
            $next = $today->copy()->addMonthNoOverflow()->startOfMonth();
            $cutoff = $next->copy()->day(min($day, $next->daysInMonth));
        }

        return ['cutoff' => $cutoff, 'pay_by' => $cutoff->copy()->endOfMonth()->startOfDay()];
    }

    /**
     * Daily safety net. Catches a missed calculation and nags about approval and payment deadlines.
     *
     * @return array<int, string> what was done
     */
    public function reminders(): array
    {
        $today = now()->startOfDay();
        $cutoffDay = (int) config('afisab.distribution_cutoff_day');
        $done = [];

        $run = DistributionRun::where('period', $today->format('Y-m'))->first();

        if (! $run && $today->day > $cutoffDay) {
            $run = $this->calculate($today);
            $done[] = 'calculated (catch-up)';
        }

        if (! $run || ($run->last_reminder_at && $run->last_reminder_at->isSameDay($today))) {
            return $done;
        }

        $daysLeft = (int) $today->diffInDays($run->pay_by, false);

        if ($run->status === DistributionRun::CALCULATED && $today->day > $cutoffDay) {
            $this->notify($this->approvers(), $run, "URGENT: approve the {$run->label()} profit distribution",
                "Investors must be paid by {$run->pay_by->format('d F Y')} ({$daysLeft} day(s) left). The run is still awaiting approval.");
            $run->update(['last_reminder_at' => now()]);
            $done[] = 'approval reminder';
        } elseif ($run->status === DistributionRun::APPROVED && $daysLeft <= 2) {
            $this->notify($this->payers(), $run, "Pay investors: {$run->label()} profit distribution",
                "Payment is due by {$run->pay_by->format('d F Y')}. Some investors are still unpaid.");
            $run->update(['last_reminder_at' => now()]);
            $done[] = 'payment reminder';
        }

        return $done;
    }

    private function closeIfFullyPaid(DistributionRun $run): void
    {
        if ($run->status === DistributionRun::APPROVED && ! $run->lines()->where('status', DistributionLine::PAYABLE)->exists()) {
            $run->update(['status' => DistributionRun::PAID, 'paid_at' => now()]);
        }
    }

    /** Spreadsheet formula injection guard for exported text. */
    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function approvers()
    {
        return User::role('super-admin')->get();
    }

    private function payers()
    {
        return User::role(['accounts', 'super-admin'])->get();
    }

    private function notify($users, DistributionRun $run, string $subject, string $line): void
    {
        foreach ($users as $user) {
            $user->notify(new DistributionNotice($run, $subject, $line));
        }
    }
}
