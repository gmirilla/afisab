<?php

namespace App\Services;

use App\Enums\InvestmentStatus;
use App\Models\Investment;
use App\Notifications\MaturityApproaching;

class MaturityService
{
    /**
     * Daily job: remind investors of upcoming maturity (once) and mark due policies as matured.
     *
     * @return array{reminded: int, matured: int}
     */
    public function process(): array
    {
        $today = now()->startOfDay();
        $reminded = $matured = 0;

        Investment::with('user')
            ->where('status', InvestmentStatus::Active)
            ->whereNull('maturity_reminder_sent_at')
            ->whereDate('maturity_date', '<=', $today->copy()->addDays((int) config('afisab.maturity_reminder_days')))
            ->each(function (Investment $investment) use (&$reminded) {
                $investment->user->notify(new MaturityApproaching($investment));
                $investment->update(['maturity_reminder_sent_at' => now()]);
                $reminded++;
            });

        Investment::where('status', InvestmentStatus::Active)
            ->whereDate('maturity_date', '<=', $today)
            ->each(function (Investment $investment) use (&$matured) {
                $investment->update(['status' => InvestmentStatus::Matured]);
                activity()->performedOn($investment)->log('Investment matured');
                $matured++;
            });

        return ['reminded' => $reminded, 'matured' => $matured];
    }
}
