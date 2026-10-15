<?php

namespace App\Services\Trading;

use App\Exceptions\BusinessRuleException;
use App\Models\Buyer;
use App\Models\InventoryLot;
use App\Models\LedgerAccount;
use App\Models\Sale;
use App\Models\TradeCycle;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Selling stock. Confirming a sale reserves the stock (so it cannot be sold twice) and fixes its cost;
 * the revenue is booked when Accounts records the buyer's payment, which lands in the Investors Profit Account.
 */
class SaleService
{
    public function __construct(private LedgerService $ledger) {}

    public function create(TradeCycle $cycle, Buyer $buyer, InventoryLot $lot, int $quantityKg, int $unitPrice, User $by, ?string $note = null): Sale
    {
        if (! $cycle->isOpen()) {
            throw new BusinessRuleException('This trading cycle is no longer open.');
        }
        if (! $buyer->is_active) {
            throw new BusinessRuleException('That buyer is not active.');
        }
        if ($lot->trade_cycle_id !== $cycle->id) {
            throw new BusinessRuleException('That stock belongs to a different cycle.');
        }
        if (! $lot->qa_passed) {
            throw new BusinessRuleException('Stock that failed quality checks cannot be sold.');
        }
        if ($quantityKg <= 0 || $unitPrice <= 0) {
            throw new BusinessRuleException('Quantity and price must be greater than zero.');
        }
        if ($quantityKg > $lot->remaining_kg) {
            throw new BusinessRuleException('Only '.number_format($lot->remaining_kg).' kg is left in that lot.');
        }

        $sale = Sale::create([
            'reference' => 'SAL-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
            'trade_cycle_id' => $cycle->id,
            'buyer_id' => $buyer->id,
            'inventory_lot_id' => $lot->id,
            'quantity_kg' => $quantityKg,
            'unit_price' => $unitPrice,
            'total' => $quantityKg * $unitPrice,
            'status' => Sale::DRAFT,
            'created_by' => $by->id,
            'note' => $note,
        ]);
        activity()->performedOn($sale)->causedBy($by)->log('Sale drafted');

        return $sale;
    }

    /** Reserve the stock and fix the cost of goods. */
    public function confirm(Sale $sale, User $by): Sale
    {
        return DB::transaction(function () use ($sale, $by) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->with('cycle')->firstOrFail();
            $lot = InventoryLot::whereKey($sale->inventory_lot_id)->lockForUpdate()->firstOrFail();

            if ($sale->status !== Sale::DRAFT) {
                throw new BusinessRuleException('Only a draft sale can be confirmed.');
            }
            if (! $sale->cycle->isOpen()) {
                throw new BusinessRuleException('This trading cycle is no longer open.');
            }
            if ($sale->quantity_kg > $lot->remaining_kg) {
                throw new BusinessRuleException('Only '.number_format($lot->remaining_kg).' kg is left in that lot.');
            }

            // The last kilogram carries the rounding remainder so the lot's cost is fully consumed.
            $cost = $sale->quantity_kg === $lot->remaining_kg
                ? $lot->remaining_cost
                : intdiv($lot->remaining_cost * $sale->quantity_kg, $lot->remaining_kg);

            $lot->update(['remaining_kg' => $lot->remaining_kg - $sale->quantity_kg, 'remaining_cost' => $lot->remaining_cost - $cost]);
            $sale->update(['status' => Sale::CONFIRMED, 'cost_of_goods' => $cost, 'confirmed_at' => now()]);
            activity()->performedOn($sale)->causedBy($by)->log('Sale confirmed; stock reserved');

            return $sale;
        });
    }

    /** Cancel a draft, or a confirmed sale that has not been paid (the stock goes back). */
    public function cancel(Sale $sale, User $by): Sale
    {
        return DB::transaction(function () use ($sale, $by) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if (! in_array($sale->status, [Sale::DRAFT, Sale::CONFIRMED], true)) {
                throw new BusinessRuleException('A paid or cancelled sale cannot be cancelled.');
            }

            if ($sale->status === Sale::CONFIRMED) {
                $lot = InventoryLot::whereKey($sale->inventory_lot_id)->lockForUpdate()->firstOrFail();
                $lot->update(['remaining_kg' => $lot->remaining_kg + $sale->quantity_kg, 'remaining_cost' => $lot->remaining_cost + $sale->cost_of_goods]);
            }

            $sale->update(['status' => Sale::CANCELLED]);
            activity()->performedOn($sale)->causedBy($by)->log('Sale cancelled');

            return $sale;
        });
    }

    /** The buyer has paid into the Investors Profit Account: book the revenue and the cost of goods. */
    public function receivePayment(Sale $sale, string $reference, User $by): Sale
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new BusinessRuleException('A payment reference is required.');
        }

        return DB::transaction(function () use ($sale, $reference, $by) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->with('cycle')->firstOrFail();

            if ($sale->status !== Sale::CONFIRMED) {
                throw new BusinessRuleException('Only a confirmed sale can be marked as paid.');
            }

            $margin = $sale->total - $sale->cost_of_goods;
            $lines = [['account' => LedgerAccount::INVESTOR_PROFIT, 'direction' => 'debit', 'amount' => $sale->total, 'subject' => $sale->cycle]];
            $lines[] = ['account' => LedgerAccount::INVENTORY, 'direction' => 'credit', 'amount' => $sale->cost_of_goods, 'subject' => $sale->cycle];
            if ($margin > 0) {
                $lines[] = ['account' => LedgerAccount::TRADING_INCOME, 'direction' => 'credit', 'amount' => $margin, 'subject' => $sale->cycle];
            } elseif ($margin < 0) {
                $lines[] = ['account' => LedgerAccount::TRADING_INCOME, 'direction' => 'debit', 'amount' => -$margin, 'subject' => $sale->cycle];
            }

            $this->ledger->post("Sale {$sale->reference} paid by buyer", $lines, "{$sale->reference}-RCV", $sale->cycle, $by->id);

            $sale->update(['status' => Sale::PAID, 'received_by' => $by->id, 'received_at' => now(), 'payment_reference' => $reference]);
            activity()->performedOn($sale)->causedBy($by)->withProperties(['reference' => $reference])->log('Buyer payment received');

            return $sale;
        });
    }
}
