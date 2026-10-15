<?php

namespace App\Services\Trading;

use App\Enums\InvestmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Commodity;
use App\Models\CycleAllocation;
use App\Models\CycleExpense;
use App\Models\Investment;
use App\Models\LedgerAccount;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\TradeCycle;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Performance\PerformanceService;
use App\Services\Risk\RiskService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A trading cycle buys one commodity with allocated investor capital, stores it, sells it and closes.
 *
 * Money flow (see docs/ACCOUNTING.md):
 *   purchase paid      Dr Inventory           / Cr Main Operations
 *   expense paid       Dr Trading Expenses    / Cr Main Operations
 *   buyer pays         Dr Investors Profit    / Cr Inventory (cost) + Cr Trading Income (margin)
 *   cycle closes       Dr Main Operations     / Cr Investors Profit   (sales proceeds less the 20% retention)
 * and the cycle's net profit is shared across the allocated policies for the monthly distribution.
 */
class CycleService
{
    public function __construct(private LedgerService $ledger, private PerformanceService $performance, private RiskService $risk) {}

    public function open(string $name, Commodity $commodity, ?string $notes, User $by): TradeCycle
    {
        if (! $commodity->is_active) {
            throw new BusinessRuleException('That commodity is not active.');
        }

        $cycle = TradeCycle::create([
            'reference' => 'CYC-'.now()->format('ym').'-'.strtoupper(Str::random(4)),
            'name' => $name,
            'commodity_id' => $commodity->id,
            'status' => TradeCycle::OPEN,
            'notes' => $notes,
            'opened_by' => $by->id,
            'opened_at' => now(),
        ]);
        activity()->performedOn($cycle)->causedBy($by)->log('Trading cycle opened');

        return $cycle;
    }

    /** Capital of a policy not already committed to another open cycle. */
    public function unallocated(Investment $investment, ?TradeCycle $except = null): int
    {
        $used = CycleAllocation::where('investment_id', $investment->id)
            ->whereHas('cycle', fn ($q) => $q->where('status', TradeCycle::OPEN))
            ->when($except, fn ($q) => $q->where('trade_cycle_id', '!=', $except->id))
            ->sum('amount');

        return $investment->amount - (int) $used;
    }

    /** Allocate (or change the allocation of) a policy's capital to a cycle. */
    public function allocate(TradeCycle $cycle, Investment $investment, int $amount, User $by): CycleAllocation
    {
        return DB::transaction(function () use ($cycle, $investment, $amount, $by) {
            $cycle = TradeCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $investment = Investment::whereKey($investment->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($cycle);

            if ($investment->status !== InvestmentStatus::Active) {
                throw new BusinessRuleException('Only active investments can be allocated to a cycle.');
            }
            if ($amount <= 0) {
                throw new BusinessRuleException('The allocation must be greater than zero.');
            }
            $available = $this->unallocated($investment, $cycle);
            if ($amount > $available) {
                throw new BusinessRuleException('Only '.Money::format($available).' of this investment is free to allocate.');
            }

            $existing = $cycle->allocations()->where('investment_id', $investment->id)->first();
            $newBudget = $cycle->budget() - ($existing?->amount ?? 0) + $amount;
            if ($newBudget < $cycle->committed()) {
                throw new BusinessRuleException('That would take the cycle budget below what is already committed to purchases.');
            }

            $this->risk->assertCycleBudget($newBudget);
            $this->risk->assertDeployment($amount - ($existing?->amount ?? 0));

            $allocation = $cycle->allocations()->updateOrCreate(['investment_id' => $investment->id], ['amount' => $amount]);
            activity()->performedOn($cycle)->causedBy($by)->withProperties(['investment' => $investment->reference, 'amount' => $amount])->log('Capital allocated to cycle');

            return $allocation;
        });
    }

    public function removeAllocation(CycleAllocation $allocation, User $by): void
    {
        DB::transaction(function () use ($allocation, $by) {
            $cycle = TradeCycle::whereKey($allocation->trade_cycle_id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($cycle);

            if ($cycle->budget() - $allocation->amount < $cycle->committed()) {
                throw new BusinessRuleException('Removing this allocation would take the budget below what is already committed to purchases.');
            }
            $allocation->delete();
            activity()->performedOn($cycle)->causedBy($by)->log('Allocation removed from cycle');
        });
    }

    /** Cancel a cycle in which no money has moved. */
    public function cancel(TradeCycle $cycle, User $by): TradeCycle
    {
        return DB::transaction(function () use ($cycle, $by) {
            $cycle = TradeCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($cycle);

            $moved = $cycle->purchases()->whereIn('status', [Purchase::PAID, Purchase::RECEIVED])->exists()
                || $cycle->expenses()->where('status', CycleExpense::PAID)->exists()
                || $cycle->sales()->where('status', Sale::PAID)->exists();
            if ($moved) {
                throw new BusinessRuleException('Money has already moved in this cycle; it must be closed, not cancelled.');
            }

            $cycle->purchases()->whereIn('status', [Purchase::PROPOSED, Purchase::APPROVED])->update(['status' => Purchase::CANCELLED]);
            $cycle->expenses()->where('status', CycleExpense::PROPOSED)->update(['status' => CycleExpense::REJECTED]);
            $cycle->update(['status' => TradeCycle::CANCELLED, 'closed_by' => $by->id, 'closed_at' => now()]);
            activity()->performedOn($cycle)->causedBy($by)->log('Trading cycle cancelled');

            return $cycle;
        });
    }

    /**
     * Close the cycle: settle the proceeds, work out the profit and hand the result to the monthly distribution.
     * Everything must be finished first (no unpaid items, all stock sold).
     */
    public function close(TradeCycle $cycle, User $by): TradeCycle
    {
        return DB::transaction(function () use ($cycle, $by) {
            $cycle = TradeCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($cycle);

            $this->assertReadyToClose($cycle);

            $purchases = (int) $cycle->purchases()->whereIn('status', [Purchase::PAID, Purchase::RECEIVED])->sum('total');
            $expenses = (int) $cycle->expenses()->where('status', CycleExpense::PAID)->sum('amount');
            $sales = (int) $cycle->sales()->where('status', Sale::PAID)->sum('total');
            $net = $sales - $purchases - $expenses;

            $retained = $net > 0 ? LedgerService::bps($net, (int) config('afisab.profit_retention_bps')) : 0;
            $returned = $sales - $retained;
            if ($returned > 0) {
                $this->ledger->transfer(
                    LedgerAccount::INVESTOR_PROFIT,
                    LedgerAccount::MAIN_OPS,
                    $returned,
                    "Cycle {$cycle->reference} settled: capital, expenses and profit balance returned",
                    $cycle,
                    "CYC-{$cycle->reference}-SETTLE",
                    $by->id,
                );
            }

            $allocations = $cycle->allocations()->pluck('amount', 'investment_id')->all();
            $this->performance->recordCycleResult($cycle->reference, $allocations, $net);

            $cycle->update([
                'status' => TradeCycle::CLOSED, 'closed_by' => $by->id, 'closed_at' => now(),
                'total_purchases' => $purchases, 'total_expenses' => $expenses, 'total_sales' => $sales,
                'net_profit' => $net, 'retained' => $retained,
            ]);
            activity()->performedOn($cycle)->causedBy($by)->withProperties(['net_profit' => $net])->log('Trading cycle closed');

            return $cycle;
        });
    }

    private function assertReadyToClose(TradeCycle $cycle): void
    {
        $problems = [];

        if ($cycle->allocations()->doesntExist()) {
            $problems[] = 'no investor capital was allocated';
        }
        if ($cycle->purchases()->whereIn('status', [Purchase::PROPOSED, Purchase::APPROVED])->exists()) {
            $problems[] = 'there are purchases not yet paid (pay, reject or cancel them)';
        }
        if ($cycle->purchases()->where('status', Purchase::PAID)->exists()) {
            $problems[] = 'there are paid purchases whose goods have not been received';
        }
        if ($cycle->expenses()->where('status', CycleExpense::PROPOSED)->exists()) {
            $problems[] = 'there are expenses not yet paid or rejected';
        }
        if ($cycle->sales()->whereIn('status', [Sale::DRAFT, Sale::CONFIRMED])->exists()) {
            $problems[] = 'there are sales not yet paid or cancelled';
        }
        if ($cycle->lots()->where('remaining_kg', '>', 0)->exists()) {
            $problems[] = 'unsold stock remains in the warehouse';
        }
        if ($cycle->sales()->where('status', Sale::PAID)->doesntExist()) {
            $problems[] = 'nothing has been sold';
        }

        if ($problems) {
            throw new BusinessRuleException('This cycle cannot be closed yet: '.implode('; ', $problems).'.');
        }
    }

    private function assertOpen(TradeCycle $cycle): void
    {
        if (! $cycle->isOpen()) {
            throw new BusinessRuleException('This trading cycle is no longer open.');
        }
    }
}
