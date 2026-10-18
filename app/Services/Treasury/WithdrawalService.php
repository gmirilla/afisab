<?php

namespace App\Services\Treasury;

use App\Enums\InvestmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CycleAllocation;
use App\Models\Investment;
use App\Models\TradeCycle;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Notifications\WithdrawalMatured;
use App\Notifications\WithdrawalRequestedAlert;
use App\Notifications\WithdrawalRequestReceived;
use Illuminate\Support\Facades\DB;

/**
 * Early withdrawal, as the agreement describes it (clause 9.2): the investor gives notice, the notice runs for the
 * period frozen on their policy (one month by default), and the withdrawal is "subject to the availability of funds
 * and settlement of ongoing transactions".
 *
 * Built as an EARLY MATURITY so nothing downstream is reinvented:
 *
 *   investor gives notice
 *     -> notice period runs; the policy stays active and keeps earning, but its capital is no longer put into new cycles
 *     -> when the notice has run AND none of the policy's capital is still in a pending or open trading cycle
 *        ("ongoing transactions settled"), the policy matures early: status Matured, maturity date = that day
 *     -> from here it is the ordinary maturity path: the final profit run settles any profit still held back for the
 *        policy's payment frequency, and the capital payout is prepared, approved by a Director and paid by Accounts
 *        (the cash guard there is the "availability of funds").
 */
class WithdrawalService
{
    public function request(Investment $investment, User $by, ?string $reason = null): WithdrawalRequest
    {
        $request = DB::transaction(function () use ($investment, $by, $reason) {
            $investment = Investment::whereKey($investment->id)->lockForUpdate()->firstOrFail();

            if ($investment->status !== InvestmentStatus::Active) {
                throw new BusinessRuleException('Only an active investment can give notice of early withdrawal.');
            }
            if ($this->open($investment)) {
                throw new BusinessRuleException('You have already given notice of withdrawal for this investment.');
            }

            $months = (int) ($investment->terms['early_withdrawal_notice_months'] ?? config('afisab.policy.early_withdrawal_notice_months'));
            $noticeEnds = now()->startOfDay()->addMonthsNoOverflow($months);
            if ($investment->maturity_date !== null && $investment->maturity_date->lte($noticeEnds)) {
                throw new BusinessRuleException('This investment matures on '.$investment->maturity_date->format('d M Y').', before a notice period of '.$months.' month'.($months === 1 ? '' : 's').' would end. It will be paid out at maturity.');
            }

            $request = WithdrawalRequest::create([
                'investment_id' => $investment->id, 'status' => WithdrawalRequest::REQUESTED, 'requested_by' => $by->id, 'requested_at' => now(),
                'notice_ends_on' => $noticeEnds, 'reason' => $reason ? trim($reason) : null, 'original_maturity_date' => $investment->maturity_date,
            ]);
            activity()->performedOn($investment)->causedBy($by)->withProperties(['notice_ends_on' => $noticeEnds->toDateString(), 'notice_months' => $months])->log('Early withdrawal notice given');

            return $request;
        });

        $investment->loadMissing('user');
        $investment->user->notify(new WithdrawalRequestReceived($request));
        foreach ($this->staff() as $staff) {
            $staff->notify(new WithdrawalRequestedAlert($request));
        }

        return $request;
    }

    public function cancel(WithdrawalRequest $request, User $by, ?string $note = null): WithdrawalRequest
    {
        return DB::transaction(function () use ($request, $by, $note) {
            $request = WithdrawalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isOpen()) {
                throw new BusinessRuleException('Only a withdrawal notice that is still running can be cancelled.');
            }
            $request->update(['status' => WithdrawalRequest::CANCELLED, 'cancelled_at' => now(), 'cancelled_by' => $by->id, 'cancel_note' => $note]);
            activity()->performedOn($request->investment)->causedBy($by)->withProperties(['note' => $note])->log('Early withdrawal notice cancelled');

            return $request;
        });
    }

    /**
     * What is still holding the withdrawal back, in words. Empty means it can proceed.
     *
     * @return list<string>
     */
    public function blockers(WithdrawalRequest $request): array
    {
        $blockers = [];
        if (! $request->noticeElapsed()) {
            $blockers[] = 'The notice period runs until '.$request->notice_ends_on->format('d M Y').'.';
        }

        $cycles = TradeCycle::whereIn('status', TradeCycle::HOLDING)
            ->whereHas('allocations', fn ($q) => $q->where('investment_id', $request->investment_id))->pluck('reference');
        if ($cycles->isNotEmpty()) {
            $blockers[] = 'Capital is still in trading cycle '.$cycles->implode(', ').'. Close or cancel the cycle, or remove this policy\'s allocation, first.';
        }

        return $blockers;
    }

    /** Mature the policy early. $by is null when the daily job does it. */
    public function process(WithdrawalRequest $request, ?User $by = null): WithdrawalRequest
    {
        $request = DB::transaction(function () use ($request, $by) {
            $request = WithdrawalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $investment = Investment::whereKey($request->investment_id)->lockForUpdate()->firstOrFail();

            if (! $request->isOpen()) {
                throw new BusinessRuleException('This withdrawal has already been processed or cancelled.');
            }
            if ($blockers = $this->blockers($request)) {
                throw new BusinessRuleException(implode(' ', $blockers));
            }
            if ($investment->status !== InvestmentStatus::Active) {
                throw new BusinessRuleException('The investment is no longer active.');
            }

            $investment->update(['status' => InvestmentStatus::Matured, 'maturity_date' => now()->startOfDay()]);
            $request->update(['status' => WithdrawalRequest::MATURED, 'matured_at' => now(), 'matured_by' => $by?->id]);
            activity()->performedOn($investment)->causedBy($by)->withProperties(['original_maturity' => $request->original_maturity_date?->toDateString()])->log('Investment matured early at the investor\'s request');

            return $request;
        });

        $request->investment->user->notify(new WithdrawalMatured($request));

        return $request;
    }

    /**
     * The daily job: every notice that has run out and has nothing holding it back matures now.
     *
     * @return array{matured: int, waiting: int}
     */
    public function processDue(): array
    {
        $matured = $waiting = 0;
        foreach (WithdrawalRequest::where('status', WithdrawalRequest::REQUESTED)->whereDate('notice_ends_on', '<=', now()->startOfDay())->get() as $request) {
            if ($this->blockers($request) !== []) {
                $waiting++;

                continue;
            }
            try {
                $this->process($request);
                $matured++;
            } catch (BusinessRuleException) {
                $waiting++;
            }
        }

        return ['matured' => $matured, 'waiting' => $waiting];
    }

    /** The capital has been paid out: close the request. Called by the capital payout. */
    public function completed(Investment $investment): void
    {
        WithdrawalRequest::where('investment_id', $investment->id)->where('status', WithdrawalRequest::MATURED)->update(['status' => WithdrawalRequest::COMPLETED, 'completed_at' => now()]);
    }

    public function open(Investment $investment): ?WithdrawalRequest
    {
        return WithdrawalRequest::where('investment_id', $investment->id)->where('status', WithdrawalRequest::REQUESTED)->first();
    }

    /** Who is told when a notice arrives: Accounts and the Directors, active accounts only. */
    private function staff()
    {
        return User::role(['accounts', 'super-admin'])->active()->get();
    }
}
