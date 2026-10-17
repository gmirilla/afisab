<?php

namespace App\Services\Performance;

use App\Enums\InvestmentStatus;
use App\Enums\ProfitFrequency;
use App\Models\Investment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Which monthly distribution runs a policy takes part in, given the payment frequency its investor chose.
 *
 * Every month the run still sweeps trading results (see DistributionService); a policy is only included when it is
 * DUE that month. A policy that is not due keeps its results recorded and unassigned, and they are swept, netted
 * together, in the run where it falls due.
 *
 *   Monthly       due every month
 *   Quarterly     due every third month counted from the month the policy started (start + 3, 6, 9 ...)
 *   At maturity   due only in the final run
 *
 * The FINAL run (every frequency) is any run whose month ends on or after the maturity date. It keeps sweeping
 * until nothing is left, so results recorded late are never stranded.
 */
class ProfitSchedule
{
    public static function isDue(Investment $investment, CarbonInterface $month): bool
    {
        $frequency = $investment->profit_frequency ?? ProfitFrequency::Monthly;
        $monthStart = Carbon::instance($month)->startOfMonth();

        if ($frequency === ProfitFrequency::Monthly) {
            return true;
        }

        if ($investment->maturity_date !== null && $investment->maturity_date->lte($monthStart->copy()->endOfMonth())) {
            return true; // final run
        }

        if ($frequency === ProfitFrequency::Quarterly && $investment->start_date !== null) {
            $elapsed = ($monthStart->year * 12 + $monthStart->month) - ($investment->start_date->year * 12 + $investment->start_date->month);

            return $elapsed > 0 && $elapsed % 3 === 0;
        }

        return false;
    }

    /**
     * The next run this policy will be paid in.
     *
     * @return array{cutoff: Carbon, pay_by: Carbon}|null null once the policy is closed or cancelled
     */
    public static function nextRun(Investment $investment, ?CarbonInterface $from = null): ?array
    {
        if (! in_array($investment->status, [InvestmentStatus::Active, InvestmentStatus::Matured], true)) {
            return null;
        }

        $month = DistributionService::nextSchedule($from)['cutoff']->copy()->startOfMonth();
        $day = (int) config('afisab.distribution_cutoff_day');

        for ($i = 0; $i < 36; $i++) {
            if (self::isDue($investment, $month)) {
                return [
                    'cutoff' => $month->copy()->day(min($day, $month->daysInMonth)),
                    'pay_by' => $month->copy()->endOfMonth()->startOfDay(),
                ];
            }
            $month = $month->copy()->addMonthNoOverflow()->startOfMonth();
        }

        return null;
    }
}
