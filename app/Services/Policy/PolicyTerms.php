<?php

namespace App\Services\Policy;

use App\Enums\ProfitFrequency;
use App\Models\InvestmentPlan;

/**
 * The commercial terms an investor accepts, frozen on the policy when they subscribe.
 *
 * The agreement quotes these, and the monthly distribution calculates with them, so what the contract says and
 * what the software pays cannot drift apart if the company's rules change later. The profit shares are expressed as
 * percentages of NET PROFIT, the way a Mudharabah agreement states them:
 *
 *   reserve  = the retention (default 20%)
 *   investor = the investor share (default 30%) of what is left after the retention  -> 24%
 *   company  = the rest                                                              -> 56%
 *   risk fund = 0%: the 5% risk reserve is taken from capital on receipt, not from profit
 */
class PolicyTerms
{
    public const TEMPLATE_VERSION = '2026.1';

    /** @return array<string, mixed> */
    public static function snapshot(InvestmentPlan $plan, ProfitFrequency $frequency): array
    {
        $retention = (int) config('afisab.profit_retention_bps');
        $investorOfRest = (int) config('afisab.investor_profit_share_bps');
        $distributable = 10_000 - $retention;
        $investor = intdiv($distributable * $investorOfRest, 10_000);

        return [
            'template_version' => self::TEMPLATE_VERSION,
            'captured_at' => now()->toIso8601String(),
            'duration_months' => $plan->duration_months,
            'profit_frequency' => $frequency->value,
            'retention_bps' => $retention,
            'investor_share_bps' => $investorOfRest,
            'shares_bps' => [
                'investor' => $investor,
                'company' => $distributable - $investor,
                'risk_fund' => 0,
                'reserve' => $retention,
            ],
            'early_withdrawal_notice_months' => (int) config('afisab.policy.early_withdrawal_notice_months'),
            'arbitration_seat' => (string) config('afisab.policy.arbitration_seat'),
        ];
    }

    /** 2400 -> "24.00%" */
    public static function percent(int $bps): string
    {
        return number_format($bps / 100, 2).'%';
    }
}
