<?php

namespace App\Services;

use App\Enums\InvestmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Support\Facades\DB;
use App\Enums\ProfitFrequency;
use App\Exceptions\BusinessRuleException;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\User;
use App\Services\Policy\PolicyTerms;
use App\Support\Money;
use Illuminate\Support\Str;

class SubscriptionService
{
    /** @param  array{ip?: string}|null  $acceptance  set when the investor ticked agreement to the terms (the web flow); recorded with the time */
    public function subscribe(User $user, InvestmentPlan $plan, int $amount, ProfitFrequency $frequency = ProfitFrequency::Monthly, ?array $acceptance = null): Investment
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

        if (! $frequency->isAvailableFor($plan->duration_months)) {
            $options = implode(', ', array_map(fn ($f) => $f->label(), ProfitFrequency::availableFor($plan->duration_months)));
            throw new BusinessRuleException("{$frequency->label()} is not available on a {$plan->duration_months}-month plan. Choose one of: {$options}.");
        }

        $investment = $user->investments()->create([
            'reference' => 'AFI-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
            'investment_plan_id' => $plan->id,
            'amount' => $amount,
            'duration_months' => $plan->duration_months, // snapshot: later plan edits don't change this policy
            'profit_frequency' => $frequency,         // fixed for the life of the policy
            'terms' => PolicyTerms::snapshot($plan, $frequency), // what the agreement says and the distribution uses
            'terms_accepted_at' => $acceptance ? now() : null,
            'terms_accepted_ip' => $acceptance['ip'] ?? null,
            'status' => InvestmentStatus::PendingPayment,
        ]);

        activity()->performedOn($investment)->causedBy($user)->withProperties(['profit_frequency' => $frequency->value, 'terms_accepted' => (bool) $acceptance])->log('Investment subscription created');

        return $investment;
    }

    /**
     * Lapse unpaid subscription requests older than the configured number of days, and the payment attempts under them.
     * Never touches a request that has a receipt waiting for Accounts, or a card payment started within the last day
     * (the investor may be paying right now). A payment that does arrive later is still honoured: see PaymentService::confirm.
     *
     * @return int how many requests lapsed
     */
    public function expireUnpaid(?\Carbon\CarbonInterface $now = null): int
    {
        $now ??= now();
        $cutoff = $now->copy()->subDays((int) config('afisab.subscription.expire_unpaid_after_days'));
        $expired = 0;

        Investment::where('status', InvestmentStatus::PendingPayment)->where('created_at', '<', $cutoff)->orderBy('id')->each(function (Investment $investment) use ($now, &$expired) {
            DB::transaction(function () use ($investment, $now, &$expired) {
                $investment = Investment::whereKey($investment->id)->lockForUpdate()->first();
                if ($investment->status !== InvestmentStatus::PendingPayment) {
                    return;
                }
                $payments = $investment->payments()->lockForUpdate()->get();
                $busy = $payments->contains(fn ($p) => $p->status === PaymentStatus::AwaitingConfirmation
                    || ($p->status === PaymentStatus::Pending && $p->method === PaymentMethod::Paystack && $p->created_at->gt($now->copy()->subDay())));
                if ($busy) {
                    return;
                }

                $payments->where('status', PaymentStatus::Pending)->each->update(['status' => PaymentStatus::Expired, 'note' => 'The unpaid subscription request expired']);
                $investment->update(['status' => InvestmentStatus::Cancelled, 'expired_at' => $now]);
                activity()->performedOn($investment)->withProperties(['after_days' => (int) config('afisab.subscription.expire_unpaid_after_days')])->log('Unpaid subscription request expired');
                $expired++;
            });
        });

        return $expired;
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
