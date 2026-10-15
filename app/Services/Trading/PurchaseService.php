<?php

namespace App\Services\Trading;

use App\Exceptions\BusinessRuleException;
use App\Models\InventoryLot;
use App\Models\LedgerAccount;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\TradeCycle;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Risk\RiskService;
use App\Services\Treasury\TreasuryService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Buying commodities.
 *
 *   propose (procurement/sales)  ->  approve (a different person; Director if large; within cycle budget)
 *   ->  pay (Accounts, not the proposer; cash must be available)  ->  receive into stock with QA
 */
class PurchaseService
{
    public function __construct(private LedgerService $ledger, private TreasuryService $treasury, private RiskService $risk) {}

    public function propose(TradeCycle $cycle, Supplier $supplier, int $quantityKg, int $unitPrice, User $by, ?string $note = null): Purchase
    {
        if (! $cycle->isOpen()) {
            throw new BusinessRuleException('This trading cycle is no longer open.');
        }
        if (! $supplier->is_active) {
            throw new BusinessRuleException('That supplier is not active.');
        }
        if ($quantityKg <= 0 || $unitPrice <= 0) {
            throw new BusinessRuleException('Quantity and price must be greater than zero.');
        }

        $purchase = Purchase::create([
            'reference' => 'PUR-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
            'trade_cycle_id' => $cycle->id,
            'supplier_id' => $supplier->id,
            'quantity_kg' => $quantityKg,
            'unit_price' => $unitPrice,
            'total' => $quantityKg * $unitPrice,
            'status' => Purchase::PROPOSED,
            'proposed_by' => $by->id,
            'note' => $note,
        ]);
        activity()->performedOn($purchase)->causedBy($by)->log('Purchase proposed');

        return $purchase;
    }

    public function approve(Purchase $purchase, User $by): Purchase
    {
        return DB::transaction(function () use ($purchase, $by) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $cycle = TradeCycle::whereKey($purchase->trade_cycle_id)->lockForUpdate()->firstOrFail();

            if ($purchase->status !== Purchase::PROPOSED) {
                throw new BusinessRuleException('Only a proposed purchase can be approved.');
            }
            if (! $cycle->isOpen()) {
                throw new BusinessRuleException('This trading cycle is no longer open.');
            }
            if ($purchase->proposed_by === $by->id) {
                throw new BusinessRuleException('A purchase must be approved by someone other than the person who proposed it.');
            }
            if ($purchase->total > $this->risk->directorThreshold() && ! $by->hasRole('super-admin')) {
                throw new BusinessRuleException('Purchases above '.Money::format($this->risk->directorThreshold()).' must be approved by a Director.');
            }
            if ($cycle->committed() + $purchase->total > $cycle->budget()) {
                throw new BusinessRuleException('This purchase would exceed the cycle budget ('.Money::format($cycle->budget()).' allocated, '.Money::format($cycle->committed()).' already committed).');
            }

            $this->risk->assertSupplierExposure($purchase->supplier, $purchase->total);
            $this->risk->assertCommodityExposure($cycle->commodity, $purchase->total);

            $purchase->update(['status' => Purchase::APPROVED, 'approved_by' => $by->id, 'approved_at' => now()]);
            activity()->performedOn($purchase)->causedBy($by)->log('Purchase approved');

            return $purchase;
        });
    }

    public function reject(Purchase $purchase, User $by, string $note): Purchase
    {
        if (! in_array($purchase->status, [Purchase::PROPOSED, Purchase::APPROVED], true)) {
            throw new BusinessRuleException('This purchase can no longer be rejected.');
        }

        $purchase->update(['status' => Purchase::REJECTED, 'note' => $note, 'approved_by' => $by->id, 'approved_at' => now()]);
        activity()->performedOn($purchase)->causedBy($by)->withProperties(['note' => $note])->log('Purchase rejected');

        return $purchase;
    }

    /** Record that Accounts paid the supplier. Posts Dr Inventory / Cr Main Operations. */
    public function pay(Purchase $purchase, string $reference, User $by): Purchase
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new BusinessRuleException('A payment reference is required.');
        }

        return DB::transaction(function () use ($purchase, $reference, $by) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->with('cycle')->firstOrFail();

            if ($purchase->status !== Purchase::APPROVED) {
                throw new BusinessRuleException('Only an approved purchase can be paid.');
            }
            if ($purchase->proposed_by === $by->id) {
                throw new BusinessRuleException('The person who proposed a purchase cannot also pay it.');
            }
            $this->treasury->assertCanPay($purchase->total);

            $this->ledger->post(
                "Commodity purchase {$purchase->reference}",
                [
                    ['account' => LedgerAccount::INVENTORY, 'direction' => 'debit', 'amount' => $purchase->total, 'subject' => $purchase->cycle],
                    ['account' => LedgerAccount::MAIN_OPS, 'direction' => 'credit', 'amount' => $purchase->total, 'subject' => $purchase->cycle],
                ],
                "{$purchase->reference}-PAY",
                $purchase->cycle,
                $by->id,
            );

            $purchase->update(['status' => Purchase::PAID, 'paid_by' => $by->id, 'paid_at' => now(), 'payment_reference' => $reference]);
            activity()->performedOn($purchase)->causedBy($by)->withProperties(['reference' => $reference])->log('Purchase paid');

            return $purchase;
        });
    }

    /**
     * Goods arrive: record quantity and quality checks and put them into stock.
     *
     * @param  array{quantity_kg: int, warehouse?: ?string, grade?: ?string, moisture_percent?: ?float, qa_passed: bool, qa_notes?: ?string}  $data
     */
    public function receive(Purchase $purchase, array $data, User $by): InventoryLot
    {
        return DB::transaction(function () use ($purchase, $data, $by) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->with('cycle')->firstOrFail();

            if ($purchase->status !== Purchase::PAID) {
                throw new BusinessRuleException('Only a paid purchase can be received.');
            }
            $quantity = (int) $data['quantity_kg'];
            if ($quantity <= 0 || $quantity > $purchase->quantity_kg) {
                throw new BusinessRuleException('Received quantity must be between 1 kg and the ordered '.number_format($purchase->quantity_kg).' kg.');
            }
            if (! $purchase->cycle->isOpen()) {
                throw new BusinessRuleException('This trading cycle is no longer open.');
            }

            $lot = InventoryLot::create([
                'trade_cycle_id' => $purchase->trade_cycle_id,
                'purchase_id' => $purchase->id,
                'commodity_id' => $purchase->cycle->commodity_id,
                'warehouse' => $data['warehouse'] ?? null,
                'quantity_kg' => $quantity,
                'remaining_kg' => $quantity,
                'cost_total' => $purchase->total, // everything paid is the cost of what arrived
                'remaining_cost' => $purchase->total,
                'grade' => $data['grade'] ?? null,
                'moisture_percent' => $data['moisture_percent'] ?? null,
                'qa_passed' => (bool) $data['qa_passed'],
                'qa_notes' => $data['qa_notes'] ?? null,
                'received_by' => $by->id,
                'received_at' => now(),
            ]);

            $purchase->update(['status' => Purchase::RECEIVED, 'received_at' => now()]);
            activity()->performedOn($purchase)->causedBy($by)->withProperties(['kg' => $quantity, 'qa_passed' => $lot->qa_passed])->log('Goods received into stock');

            return $lot;
        });
    }

    public function cancel(Purchase $purchase, User $by): Purchase
    {
        if (! in_array($purchase->status, [Purchase::PROPOSED, Purchase::APPROVED], true)) {
            throw new BusinessRuleException('Only an unpaid purchase can be cancelled.');
        }

        $purchase->update(['status' => Purchase::CANCELLED]);
        activity()->performedOn($purchase)->causedBy($by)->log('Purchase cancelled');

        return $purchase;
    }
}
