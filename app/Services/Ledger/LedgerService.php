<?php

namespace App\Services\Ledger;

use App\Exceptions\LedgerException;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Double-entry posting and balance queries.
 *
 * Only Eloquent / query-builder calls are used (no raw SQL), so it behaves the same on
 * MySQL, MariaDB, PostgreSQL, SQLite and SQL Server. Amounts are integer kobo.
 */
class LedgerService
{
    /**
     * Post one balanced journal entry atomically.
     *
     * @param  array<int, array{account: string, direction: string, amount: int, subject?: Model|null}>  $lines
     */
    public function post(string $description, array $lines, ?string $reference = null, ?Model $subject = null, ?int $postedBy = null): JournalEntry
    {
        $this->assertBalanced($lines);

        return DB::transaction(function () use ($description, $lines, $reference, $subject, $postedBy) {
            $entry = new JournalEntry([
                'reference' => $reference ?? 'JE-'.strtoupper(Str::ulid()),
                'description' => $description,
                'posted_at' => now(),
                'posted_by' => $postedBy ?? auth()->id(),
            ]);
            if ($subject) {
                $entry->subject()->associate($subject);
            }
            $entry->save();

            $accounts = LedgerAccount::whereIn('code', array_unique(array_column($lines, 'account')))
                ->get()->keyBy('code');

            foreach ($lines as $line) {
                $account = $accounts[$line['account']] ?? throw new LedgerException("Unknown ledger account [{$line['account']}].");
                $lineSubject = $line['subject'] ?? null;

                $entry->lines()->create([
                    'ledger_account_id' => $account->id,
                    'direction' => $line['direction'],
                    'amount' => $line['amount'],
                    'subject_type' => $lineSubject?->getMorphClass(),
                    'subject_id' => $lineSubject?->getKey(),
                ]);
            }

            return $entry->load('lines');
        });
    }

    /** Move money between two accounts: debit $to, credit $from. */
    public function transfer(string $from, string $to, int $amount, string $description, ?Model $subject = null, ?string $reference = null, ?int $postedBy = null): JournalEntry
    {
        return $this->post($description, [
            ['account' => $to, 'direction' => JournalLine::DEBIT, 'amount' => $amount, 'subject' => $subject],
            ['account' => $from, 'direction' => JournalLine::CREDIT, 'amount' => $amount, 'subject' => $subject],
        ], $reference, $subject, $postedBy);
    }

    /** Natural-sign balance of an account in kobo, optionally restricted to one subject (e.g. one policy). */
    public function balance(string|LedgerAccount $account, ?Model $subject = null, ?CarbonInterface $asOf = null): int
    {
        $account = $account instanceof LedgerAccount ? $account : LedgerAccount::byCode($account);

        $sum = function (string $direction) use ($account, $subject, $asOf): int {
            $query = JournalLine::where('ledger_account_id', $account->id)->where('direction', $direction);
            if ($subject) {
                $query->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey());
            }
            if ($asOf) {
                $query->whereHas('entry', fn ($q) => $q->where('posted_at', '<=', $asOf));
            }

            return (int) $query->sum('amount');
        };

        $debits = $sum(JournalLine::DEBIT);
        $credits = $sum(JournalLine::CREDIT);

        return $account->isDebitNormal() ? $debits - $credits : $credits - $debits;
    }

    /**
     * Total debits and credits posted to an account, optionally within a date range (inclusive).
     *
     * @return array{debits: int, credits: int}
     */
    public function totals(string|LedgerAccount $account, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $account = $account instanceof LedgerAccount ? $account : LedgerAccount::byCode($account);

        $sum = function (string $direction) use ($account, $from, $to): int {
            return (int) JournalLine::where('ledger_account_id', $account->id)->where('direction', $direction)
                ->whereHas('entry', function ($q) use ($from, $to) {
                    if ($from) {
                        $q->where('posted_at', '>=', $from);
                    }
                    if ($to) {
                        $q->where('posted_at', '<=', $to);
                    }
                })->sum('amount');
        };

        return ['debits' => $sum(JournalLine::DEBIT), 'credits' => $sum(JournalLine::CREDIT)];
    }

    /**
     * Balance of one account split by subject (e.g. per investment policy) in a single pass.
     * Aggregated in PHP rather than with vendor-specific SQL so it stays database-agnostic.
     *
     * @return array<int, int> subject id => natural-sign balance (kobo)
     */
    public function balancesBySubject(string $accountCode, string $subjectType): array
    {
        $account = LedgerAccount::byCode($accountCode);
        $totals = [];

        JournalLine::where('ledger_account_id', $account->id)
            ->where('subject_type', $subjectType)
            ->select(['id', 'subject_id', 'direction', 'amount'])
            ->lazyById(1000)
            ->each(function (JournalLine $line) use (&$totals, $account) {
                $grows = ($line->direction === JournalLine::DEBIT) === $account->isDebitNormal();
                $totals[$line->subject_id] = ($totals[$line->subject_id] ?? 0) + ($grows ? $line->amount : -$line->amount);
            });

        return $totals;
    }

    /** Integer percentage in basis points, rounded down so we never allocate more than we hold. */
    public static function bps(int $amount, int $bps): int
    {
        return intdiv($amount * $bps, 10_000);
    }

    /** @param array<int, array{direction: string, amount: int}> $lines */
    private function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw new LedgerException('A journal entry needs at least two lines.');
        }

        $debits = $credits = 0;
        foreach ($lines as $line) {
            if (! is_int($line['amount']) || $line['amount'] <= 0) {
                throw new LedgerException('Line amounts must be positive integers (kobo).');
            }
            match ($line['direction']) {
                JournalLine::DEBIT => $debits += $line['amount'],
                JournalLine::CREDIT => $credits += $line['amount'],
                default => throw new LedgerException("Invalid direction [{$line['direction']}]."),
            };
        }

        if ($debits !== $credits) {
            throw new LedgerException("Entry is unbalanced: debits {$debits} != credits {$credits}.");
        }
    }
}
