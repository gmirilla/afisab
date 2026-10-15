<?php

namespace App\Services\Ledger;

use App\Exceptions\BusinessRuleException;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\LedgerReversal;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Correcting the ledger without ever editing it.
 *
 *   Accounts REQUESTS a reversal (with a reason) -> a Director APPROVES (different person)
 *   -> the system posts a mirror entry (every line flipped, same policy subjects).
 *
 * The original entry stays untouched. It can be reversed once, and a reversal cannot itself be reversed
 * (post a fresh, correct entry through the normal flow instead). Reversing never lets a bank account go negative.
 * Note: this corrects the ledger only; it does not undo the business record (a payment, a payout) that
 * produced the entry. The screen says so.
 */
class ReversalService
{
    public const PREFIX = 'REV-';

    /** Bank accounts that can never be driven below zero by a reversal. */
    private const CASH_ACCOUNTS = [LedgerAccount::MAIN_OPS, LedgerAccount::RISK_FUND, LedgerAccount::INVESTOR_PROFIT, LedgerAccount::DOMICILIARY];

    public function __construct(private LedgerService $ledger) {}

    public function request(JournalEntry $entry, string $reason, User $by): LedgerReversal
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new BusinessRuleException('A reason is required.');
        }

        return DB::transaction(function () use ($entry, $reason, $by) {
            $entry = JournalEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($this->isReversal($entry)) {
                throw new BusinessRuleException('A reversal cannot itself be reversed. Post a fresh, correct entry through the normal flow instead.');
            }
            if (LedgerReversal::where('journal_entry_id', $entry->id)->whereIn('status', [LedgerReversal::REQUESTED, LedgerReversal::APPROVED])->exists()) {
                throw new BusinessRuleException('This entry already has a pending or completed reversal.');
            }

            $reversal = LedgerReversal::create([
                'journal_entry_id' => $entry->id,
                'status' => LedgerReversal::REQUESTED,
                'reason' => $reason,
                'requested_by' => $by->id,
            ]);
            activity()->performedOn($entry)->causedBy($by)->withProperties(['reason' => $reason])->log('Ledger reversal requested');

            return $reversal;
        });
    }

    public function approve(LedgerReversal $reversal, User $by): LedgerReversal
    {
        return DB::transaction(function () use ($reversal, $by) {
            $reversal = LedgerReversal::whereKey($reversal->id)->lockForUpdate()->with('entry.lines.account')->firstOrFail();

            if ($reversal->status !== LedgerReversal::REQUESTED) {
                throw new BusinessRuleException('Only a pending reversal can be approved.');
            }
            if ($reversal->requested_by === $by->id) {
                throw new BusinessRuleException('A reversal must be approved by someone other than the person who requested it.');
            }

            $original = $reversal->entry;
            $lines = $original->lines->map(fn (JournalLine $l) => [
                'account' => $l->account->code,
                'direction' => $l->direction === JournalLine::DEBIT ? JournalLine::CREDIT : JournalLine::DEBIT,
                'amount' => $l->amount,
                'subject' => $l->subject_type ? $l->subject : null,
            ])->all();

            $mirror = $this->ledger->post(
                "Reversal of [{$original->reference}]: {$reversal->reason}",
                $lines,
                self::PREFIX.$original->reference,
                $original->subject,
                $by->id,
            );

            // Inside the transaction: a throw here rolls the mirror entry back.
            foreach (self::CASH_ACCOUNTS as $code) {
                $account = LedgerAccount::byCode($code);
                $balance = $this->ledger->balance($account);
                if ($balance < 0) {
                    throw new BusinessRuleException("Reversing this entry would leave {$account->name} below zero (".Money::format($balance).'). Nothing was changed.');
                }
            }

            $reversal->update([
                'status' => LedgerReversal::APPROVED,
                'decided_by' => $by->id,
                'decided_at' => now(),
                'reversal_entry_id' => $mirror->id,
            ]);
            activity()->performedOn($original)->causedBy($by)->withProperties(['reversal' => $mirror->reference, 'reason' => $reversal->reason])->log('Ledger reversal approved and posted');

            return $reversal;
        });
    }

    public function reject(LedgerReversal $reversal, User $by, string $note): LedgerReversal
    {
        return DB::transaction(function () use ($reversal, $by, $note) {
            $reversal = LedgerReversal::whereKey($reversal->id)->lockForUpdate()->firstOrFail();

            if ($reversal->status !== LedgerReversal::REQUESTED) {
                throw new BusinessRuleException('Only a pending reversal can be rejected.');
            }

            $reversal->update(['status' => LedgerReversal::REJECTED, 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note]);
            activity()->performedOn($reversal->entry)->causedBy($by)->withProperties(['note' => $note])->log('Ledger reversal rejected');

            return $reversal;
        });
    }

    public function isReversal(JournalEntry $entry): bool
    {
        return str_starts_with($entry->reference, self::PREFIX);
    }
}
