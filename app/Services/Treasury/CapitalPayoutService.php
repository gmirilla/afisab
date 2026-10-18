<?php

namespace App\Services\Treasury;

use App\Enums\InvestmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CapitalPayout;
use App\Models\CycleAllocation;
use App\Models\Investment;
use App\Models\LedgerAccount;
use App\Models\TradeCycle;
use App\Models\User;
use App\Notifications\CapitalPaid;
use App\Notifications\CapitalPayoutReady;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Returning an investor's principal at maturity.
 *
 *   matured policy -> Accounts PREPARES -> a Director APPROVES (different person) -> Accounts PAYS
 *
 * Cash moves only when the payout is marked paid: Dr Investor Funds Held / Cr Main Operations.
 * Early exit and rollover are not supported (DECISIONS.md Q6).
 */
class CapitalPayoutService
{
    public function __construct(private LedgerService $ledger, private TreasuryService $treasury) {}

    public function prepare(Investment $investment, User $by): CapitalPayout
    {
        $payout = DB::transaction(function () use ($investment, $by) {
            $investment = Investment::whereKey($investment->id)->lockForUpdate()->firstOrFail();

            if ($investment->status !== InvestmentStatus::Matured) {
                throw new BusinessRuleException('Only a matured investment can be paid out.');
            }
            if (CycleAllocation::where('investment_id', $investment->id)->whereHas('cycle', fn ($q) => $q->whereIn('status', TradeCycle::HOLDING))->exists()) {
                throw new BusinessRuleException('The capital of this investment is still deployed in a trading cycle (pending or open). Close or cancel the cycle first.');
            }
            if (CapitalPayout::where('investment_id', $investment->id)->where('status', '!=', CapitalPayout::REJECTED)->exists()) {
                throw new BusinessRuleException('A payout already exists for this investment.');
            }

            $payout = CapitalPayout::create([
                'investment_id' => $investment->id,
                'amount' => $investment->amount,
                'status' => CapitalPayout::PREPARED,
                'prepared_by' => $by->id,
            ]);
            activity()->performedOn($investment)->causedBy($by)->log('Capital payout prepared');

            return $payout;
        });

        foreach (User::role('super-admin')->active()->where('id', '!=', $by->id)->get() as $admin) {
            $admin->notify(new CapitalPayoutReady($payout));
        }

        return $payout;
    }

    public function approve(CapitalPayout $payout, User $by): CapitalPayout
    {
        return DB::transaction(function () use ($payout, $by) {
            $payout = CapitalPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($payout->status !== CapitalPayout::PREPARED) {
                throw new BusinessRuleException('Only a prepared payout can be approved.');
            }
            if ($payout->prepared_by === $by->id) {
                throw new BusinessRuleException('A payout must be approved by someone other than the person who prepared it.');
            }

            $payout->update(['status' => CapitalPayout::APPROVED, 'approved_by' => $by->id, 'approved_at' => now()]);
            activity()->performedOn($payout->investment)->causedBy($by)->log('Capital payout approved');

            return $payout;
        });
    }

    public function reject(CapitalPayout $payout, User $by, string $note): CapitalPayout
    {
        if (! in_array($payout->status, [CapitalPayout::PREPARED, CapitalPayout::APPROVED], true)) {
            throw new BusinessRuleException('This payout can no longer be rejected.');
        }

        $payout->update(['status' => CapitalPayout::REJECTED, 'note' => $note, 'approved_by' => $by->id, 'approved_at' => now()]);
        activity()->performedOn($payout->investment)->causedBy($by)->withProperties(['note' => $note])->log('Capital payout rejected');

        return $payout;
    }

    /** Record that Accounts has paid the investor (bank transfer made outside the system). */
    public function markPaid(CapitalPayout $payout, string $reference, User $by): CapitalPayout
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new BusinessRuleException('A payment reference is required.');
        }

        $payout = DB::transaction(function () use ($payout, $reference, $by) {
            $payout = CapitalPayout::whereKey($payout->id)->lockForUpdate()->with('investment')->firstOrFail();

            if ($payout->status !== CapitalPayout::APPROVED) {
                throw new BusinessRuleException('Only an approved payout can be paid.');
            }

            $this->treasury->assertCanPay($payout->amount);

            $this->ledger->transfer(
                LedgerAccount::MAIN_OPS,
                LedgerAccount::INVESTOR_FUNDS,
                $payout->amount,
                "Capital returned [{$payout->investment->reference}]",
                $payout->investment,
                "CAP-{$payout->investment->reference}-PAY",
            );

            $payout->update(['status' => CapitalPayout::PAID, 'paid_by' => $by->id, 'paid_at' => now(), 'payment_reference' => $reference]);
            $payout->investment->update(['status' => InvestmentStatus::PaidOut]);
            app(WithdrawalService::class)->completed($payout->investment);
            activity()->performedOn($payout->investment)->causedBy($by)->withProperties(['reference' => $reference])->log('Capital returned to investor');

            return $payout;
        });

        $payout->investment->user->notify(new CapitalPaid($payout));

        return $payout;
    }
}
