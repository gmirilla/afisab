<?php

namespace App\Services\Risk;

use App\Exceptions\BusinessRuleException;
use App\Models\LedgerAccount;
use App\Models\TakafulPolicy;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Takaful (Islamic insurance) cover. Premiums are paid from the Investors Risk Fund. */
class TakafulService
{
    public function __construct(private LedgerService $ledger) {}

    /** @param  array{provider: string, policy_number: string, description: string, premium: int, cover_amount: int, start_date: string, end_date: string}  $data */
    public function create(array $data, User $by): TakafulPolicy
    {
        if ($data['premium'] <= 0 || $data['cover_amount'] <= 0) {
            throw new BusinessRuleException('Premium and cover amount must be greater than zero.');
        }
        if ($data['end_date'] <= $data['start_date']) {
            throw new BusinessRuleException('The end date must be after the start date.');
        }

        $policy = TakafulPolicy::create($data + ['premium_status' => 'unpaid', 'created_by' => $by->id]);
        activity()->performedOn($policy)->causedBy($by)->log('Takaful policy recorded');

        return $policy;
    }

    /** Pay the premium out of the Risk Fund: Dr Insurance Expense / Cr Risk Fund. */
    public function payPremium(TakafulPolicy $policy, string $reference, User $by): TakafulPolicy
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new BusinessRuleException('A payment reference is required.');
        }

        return DB::transaction(function () use ($policy, $reference, $by) {
            $policy = TakafulPolicy::whereKey($policy->id)->lockForUpdate()->firstOrFail();

            if ($policy->premium_status === 'paid') {
                throw new BusinessRuleException('This premium has already been paid.');
            }
            $available = $this->ledger->balance(LedgerAccount::RISK_FUND);
            if ($policy->premium > $available) {
                throw new BusinessRuleException('The Risk Fund holds '.Money::format($available).', less than the premium of '.Money::format($policy->premium).'.');
            }

            $this->ledger->post(
                "Takaful premium {$policy->policy_number} ({$policy->provider})",
                [
                    ['account' => LedgerAccount::INSURANCE_EXPENSE, 'direction' => 'debit', 'amount' => $policy->premium],
                    ['account' => LedgerAccount::RISK_FUND, 'direction' => 'credit', 'amount' => $policy->premium],
                ],
                "TKF-{$policy->id}-PREMIUM",
                $policy,
                $by->id,
            );

            $policy->update(['premium_status' => 'paid', 'paid_at' => now(), 'paid_reference' => $reference]);
            activity()->performedOn($policy)->causedBy($by)->withProperties(['reference' => $reference])->log('Takaful premium paid');

            return $policy;
        });
    }
}
