<?php

namespace App\Services\Performance;

use App\Services\Ledger\LedgerService;

/**
 * The single place that encodes how a policy's monthly net profit is divided (owner decisions, 2026-10-14):
 *
 *   net profit --(20% retention, Investors Profit Account)--> retained
 *              \--> distributable --(30% investors / 70% company)
 *
 * A month with no profit distributes nothing. All rounding is down, with the remainder going to the
 * company, so the parts always sum exactly to the whole.
 */
class ProfitSplit
{
    /** @return array{retained: int, distributable: int, investor: int, company: int} */
    public function split(int $monthlyNet): array
    {
        if ($monthlyNet <= 0) {
            return ['retained' => 0, 'distributable' => $monthlyNet, 'investor' => 0, 'company' => 0];
        }

        $retained = LedgerService::bps($monthlyNet, (int) config('afisab.profit_retention_bps'));
        $distributable = $monthlyNet - $retained;
        $investor = LedgerService::bps($distributable, (int) config('afisab.investor_profit_share_bps'));

        return [
            'retained' => $retained,
            'distributable' => $distributable,
            'investor' => $investor,
            'company' => $distributable - $investor,
        ];
    }
}
