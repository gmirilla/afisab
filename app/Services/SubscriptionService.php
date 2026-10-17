<?php

namespace App\Services;

use App\Enums\InvestmentStatus;
use App\Enums\ProfitFrequency;
use App\Exceptions\BusinessRuleException;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Str;

class SubscriptionService
{
    public function subscribe(User $user, InvestmentPlan $plan, int $amount, ProfitFrequency $frequency = ProfitFrequency::Monthly): Investment
    {
        if (! $plan->is_active) {
            throw new BusinessRuleException('This plan is not currently open for subscription.');
        }

        if (! $user->investorProfile?->isApproved()) {
            throw new BusinessRuleException('Your identity verification (KYC) must be approved before you can invest.');
        }

        $minimum = max($plan->minimum_amount, (int) config('afisab.minimum_investment'));
        if ($amount < $minimum) {
            throw new BusinessRuleException('The minimum investment for this plan is '.Money::format($minimum).'.');
        }

        if ($frequency->intervalMonths() !== null && $plan->duration_months < $frequency->intervalMonths()) {
            throw new BusinessRuleException("This plan is shorter than one {$frequency->label()} payment period; choose another payment frequency.");
        }

        $investment = $user->investments()->create([
            'reference' => 'AFI-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
            'investment_plan_id' => $plan->id,
            'amount' => $amount,
            'duration_months' => $plan->duration_months, // snapshot: later plan edits don't change this policy
            'profit_frequency' => $frequency,         // fixed for the life of the policy
            'status' => InvestmentStatus::PendingPayment,
        ]);

        activity()->performedOn($investment)->causedBy($user)->withProperties(['profit_frequency' => $frequency->value])->log('Investment subscription created');

        return $investment;
    }

    public function cancel(Investment $investment): void
    {
        if ($investment->status !== InvestmentStatus::PendingPayment) {
            throw new BusinessRuleException('Only unpaid subscriptions can be cancelled.');
        }

        $investment->update(['status' => InvestmentStatus::Cancelled]);
        activity()->performedOn($investment)->log('Investment subscription cancelled');
    }
}
