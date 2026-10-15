<?php

namespace App\Http\Controllers\Admin\Trading;

use App\Http\Controllers\Concerns\AttemptsActions;
use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\InventoryLot;
use App\Models\Sale;
use App\Models\TradeCycle;
use App\Services\Trading\SaleService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    use AttemptsActions;

    public function store(Request $request, TradeCycle $cycle, SaleService $sales): RedirectResponse
    {
        $data = $request->validate([
            'buyer_id' => ['required', 'exists:buyers,id'],
            'inventory_lot_id' => ['required', 'exists:inventory_lots,id'],
            'quantity_kg' => ['required', 'integer', 'min:1', 'max:100000000'],
            'unit_price' => ['required', 'string', 'max:20'], // naira per kg
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt(
            fn () => $sales->create($cycle, Buyer::findOrFail($data['buyer_id']), InventoryLot::findOrFail($data['inventory_lot_id']), (int) $data['quantity_kg'], Money::fromNaira($data['unit_price']), $request->user(), $data['note'] ?? null),
            'Sale drafted. Confirm it to reserve the stock.',
        );
    }

    public function confirm(Request $request, Sale $sale, SaleService $sales): RedirectResponse
    {
        return $this->attempt(fn () => $sales->confirm($sale, $request->user()), 'Sale confirmed; stock reserved.');
    }

    public function cancel(Request $request, Sale $sale, SaleService $sales): RedirectResponse
    {
        return $this->attempt(fn () => $sales->cancel($sale, $request->user()), 'Sale cancelled.');
    }

    public function receive(Request $request, Sale $sale, SaleService $sales): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:64']]);

        return $this->attempt(fn () => $sales->receivePayment($sale, $data['reference'], $request->user()), 'Buyer payment recorded.');
    }
}
