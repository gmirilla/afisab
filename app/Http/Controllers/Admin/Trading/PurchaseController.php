<?php

namespace App\Http\Controllers\Admin\Trading;

use App\Http\Controllers\Concerns\AttemptsActions;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\TradeCycle;
use App\Services\Trading\PurchaseService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    use AttemptsActions;

    public function propose(Request $request, TradeCycle $cycle, PurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'quantity_kg' => ['required', 'integer', 'min:1', 'max:100000000'],
            'unit_price' => ['required', 'string', 'max:20'], // naira per kg
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt(
            fn () => $purchases->propose($cycle, Supplier::findOrFail($data['supplier_id']), (int) $data['quantity_kg'], Money::fromNaira($data['unit_price']), $request->user(), $data['note'] ?? null),
            'Purchase proposed and awaiting approval.',
        );
    }

    public function approve(Request $request, Purchase $purchase, PurchaseService $purchases): RedirectResponse
    {
        return $this->attempt(fn () => $purchases->approve($purchase, $request->user()), 'Purchase approved.');
    }

    public function reject(Request $request, Purchase $purchase, PurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:255']]);

        return $this->attempt(fn () => $purchases->reject($purchase, $request->user(), $data['note']), 'Purchase rejected.');
    }

    public function pay(Request $request, Purchase $purchase, PurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:64']]);

        return $this->attempt(fn () => $purchases->pay($purchase, $data['reference'], $request->user()), 'Purchase paid.');
    }

    public function receive(Request $request, Purchase $purchase, PurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate([
            'quantity_kg' => ['required', 'integer', 'min:1'],
            'warehouse' => ['nullable', 'string', 'max:120'],
            'grade' => ['nullable', 'string', 'max:32'],
            'moisture_percent' => ['nullable', 'numeric', 'between:0,100'],
            'qa_passed' => ['required', 'boolean'],
            'qa_notes' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt(fn () => $purchases->receive($purchase, $data, $request->user()), 'Goods received into stock.');
    }

    public function cancel(Request $request, Purchase $purchase, PurchaseService $purchases): RedirectResponse
    {
        return $this->attempt(fn () => $purchases->cancel($purchase, $request->user()), 'Purchase cancelled.');
    }
}
