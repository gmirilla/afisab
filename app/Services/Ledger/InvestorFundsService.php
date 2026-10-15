<?php

namespace App\Services\Ledger;

use App\Models\JournalLine;
use App\Models\LedgerAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Applies the account-structure rules for investor money.
 *
 * Rules (from the AFISAB account structure document):
 *  - All investor funds enter the Main Operations account first.
 *  - 5% of every new investor fund / recycled capital is moved to the Investors Risk Fund.
 *  - Sales proceeds land in the Investors Profit Account, which deducts capital and expenses,
 *    retains 20% of net profit, and returns capital + the balance of profit to Main Operations.
 */
class InvestorFundsService
{
    public function __construct(private LedgerService $ledger) {}

    /** Record an investor's confirmed payment and apply the risk-reserve transfer. */
    public function receive(int $amount, Model $policy, string $reference): void
    {
        DB::transaction(function () use ($amount, $policy, $reference) {
            $this->ledger->post("Investor funds received [{$reference}]", [
                ['account' => LedgerAccount::MAIN_OPS, 'direction' => JournalLine::DEBIT, 'amount' => $amount, 'subject' => $policy],
                ['account' => LedgerAccount::INVESTOR_FUNDS, 'direction' => JournalLine::CREDIT, 'amount' => $amount, 'subject' => $policy],
            ], "{$reference}-RCV", $policy);

            $reserve = LedgerService::bps($amount, (int) config('afisab.risk_reserve_bps'));
            if ($reserve > 0) {
                $this->ledger->transfer(
                    LedgerAccount::MAIN_OPS,
                    LedgerAccount::RISK_FUND,
                    $reserve,
                    "Risk reserve on investor funds [{$reference}]",
                    $policy,
                    "{$reference}-RISK",
                );
            }
        });
    }

    /**
     * Close a trading cycle for one policy slice.
     *
     * @return array{net_profit: int, retained: int, distributable: int}
     */
    public function settleCycle(int $salesProceeds, int $capital, int $expenses, Model $policy, string $reference): array
    {
        $netProfit = max(0, $salesProceeds - $capital - $expenses);
        $retained = LedgerService::bps($netProfit, (int) config('afisab.profit_retention_bps'));

        DB::transaction(function () use ($salesProceeds, $capital, $retained, $policy, $reference) {
            // 1. Buyer payment arrives in the Investors Profit Account.
            $this->ledger->post("Sales proceeds received [{$reference}]", [
                ['account' => LedgerAccount::INVESTOR_PROFIT, 'direction' => JournalLine::DEBIT, 'amount' => $salesProceeds, 'subject' => $policy],
                ['account' => LedgerAccount::INVENTORY, 'direction' => JournalLine::CREDIT, 'amount' => min($capital, $salesProceeds), 'subject' => $policy],
                ...($salesProceeds > $capital ? [
                    ['account' => LedgerAccount::TRADING_INCOME, 'direction' => JournalLine::CREDIT, 'amount' => $salesProceeds - $capital, 'subject' => $policy],
                ] : []),
            ], "{$reference}-SALE", $policy);

            // 2. Capital and expenses plus the non-retained profit return to Main Operations;
            //    the retained 20% stays behind as the profit reserve.
            $returned = $salesProceeds - $retained;
            if ($returned > 0) {
                $this->ledger->transfer(
                    LedgerAccount::INVESTOR_PROFIT,
                    LedgerAccount::MAIN_OPS,
                    $returned,
                    "Capital, expenses and profit balance returned [{$reference}]",
                    $policy,
                    "{$reference}-RET",
                );
            }
        });

        return [
            'net_profit' => $netProfit,
            'retained' => $retained,
            'distributable' => $netProfit - $retained,
        ];
    }
}
