<?php

namespace App\Services\Performance;

use App\Enums\InvestmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\DistributionLine;
use App\Models\Investment;
use App\Models\PerformanceEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PerformanceService
{
    /**
     * Record one trading cycle's result against the policies that funded it, pro rata to capital.
     * Called by the trading module when a cycle closes. Idempotent per (policy, cycle reference).
     * Investor profit is NOT decided here: it is decided by the monthly distribution run.
     *
     * @param  array<int, int>  $capitalByInvestment  investment id => capital allocated to the cycle (kobo)
     * @param  int  $cycleNetProfit  net profit of the whole cycle after capital and expenses (kobo, negative = loss)
     * @return Collection<int, PerformanceEntry>
     */
    public function recordCycleResult(string $cycleReference, array $capitalByInvestment, int $cycleNetProfit): Collection
    {
        $totalCapital = array_sum($capitalByInvestment);
        if ($totalCapital <= 0) {
            throw new BusinessRuleException('A cycle must have capital allocated to at least one policy.');
        }

        $investments = Investment::whereIn('id', array_keys($capitalByInvestment))->get()->keyBy('id');
        foreach ($capitalByInvestment as $id => $capital) {
            $investment = $investments[$id] ?? throw new BusinessRuleException("Unknown investment [{$id}].");
            if ($investment->status !== InvestmentStatus::Active && $investment->status !== InvestmentStatus::Matured) {
                throw new BusinessRuleException("Investment {$investment->reference} is not active.");
            }
            if ($capital <= 0 || $capital > $investment->amount) {
                throw new BusinessRuleException("Invalid capital allocation for {$investment->reference}.");
            }
        }

        $shares = $this->allocate($cycleNetProfit, $capitalByInvestment);

        return DB::transaction(function () use ($cycleReference, $capitalByInvestment, $shares) {
            $entries = collect();
            foreach ($capitalByInvestment as $id => $capital) {
                $entries->push(PerformanceEntry::firstOrCreate(
                    ['investment_id' => $id, 'cycle_reference' => $cycleReference],
                    ['capital_allocated' => $capital, 'net_profit' => $shares[$id], 'recorded_at' => now()],
                ));
            }

            return $entries;
        });
    }

    /**
     * Per-policy position. Profit counts only once a monthly run has been approved, so an investor
     * is never shown a figure the company has not committed to.
     *
     * - earned:  approved profit share (payable + paid)
     * - paid:    already paid out to the investor
     * - payable: approved, awaiting payment
     * - current_value = principal + payable (paid-out profit has left the policy)
     *
     * @return array{principal: int, earned: int, paid: int, payable: int, current_value: int, pending_entries: int, recorded_net_profit: int, cycles: int}
     */
    public function position(Investment $investment): array
    {
        $entries = $investment->performanceEntries()->with('run')->get();
        $lines = DistributionLine::where('investment_id', $investment->id)->get();

        $paid = (int) $lines->where('status', DistributionLine::PAID)->sum('investor_share');
        $payable = (int) $lines->where('status', DistributionLine::PAYABLE)->sum('investor_share');

        return [
            'principal' => $investment->amount,
            'earned' => $paid + $payable,
            'paid' => $paid,
            'payable' => $payable,
            'current_value' => $investment->amount + $payable,
            'pending_entries' => $entries->filter(fn ($e) => ! $e->run || $e->run->status === 'calculated')->count(),
            'recorded_net_profit' => (int) $entries->sum('net_profit'),
            'cycles' => $entries->count(),
        ];
    }

    /**
     * Split a cycle result across policies pro rata to capital. Uses largest-remainder rounding so the
     * parts always sum exactly to the whole (no kobo created or lost).
     *
     * @param  array<int, int>  $capitalByInvestment
     * @return array<int, int>
     */
    private function allocate(int $total, array $capitalByInvestment): array
    {
        $totalCapital = array_sum($capitalByInvestment);
        $sign = $total < 0 ? -1 : 1;
        $abs = abs($total);

        $shares = [];
        $remainders = [];
        foreach ($capitalByInvestment as $id => $capital) {
            $numerator = $abs * $capital;
            $shares[$id] = intdiv($numerator, $totalCapital);
            $remainders[$id] = $numerator % $totalCapital;
        }

        $leftover = $abs - array_sum($shares);
        arsort($remainders);
        foreach (array_keys($remainders) as $id) {
            if ($leftover <= 0) {
                break;
            }
            $shares[$id]++;
            $leftover--;
        }

        return array_map(fn ($v) => $v * $sign, $shares);
    }
}
