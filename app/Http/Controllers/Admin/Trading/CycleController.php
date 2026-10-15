<?php

namespace App\Http\Controllers\Admin\Trading;

use App\Enums\InvestmentStatus;
use App\Http\Controllers\Concerns\AttemptsActions;
use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\Commodity;
use App\Models\CycleAllocation;
use App\Models\CycleExpense;
use App\Models\Investment;
use App\Models\Supplier;
use App\Models\TradeCycle;
use App\Services\Trading\CycleService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CycleController extends Controller
{
    use AttemptsActions;

    public function index(): View
    {
        return view('admin.trading.cycles', [
            'cycles' => TradeCycle::with('commodity')->withSum('allocations as budget', 'amount')->orderByDesc('id')->get(),
            'commodities' => Commodity::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(TradeCycle $cycle, CycleService $cycles): View
    {
        $cycle->load(['commodity', 'allocations.investment.user', 'purchases.supplier', 'purchases.proposer', 'purchases.lot', 'expenses', 'lots.commodity', 'sales.buyer']);

        $allocatable = $cycle->isOpen()
            ? Investment::with('user')->where('status', InvestmentStatus::Active)->orderBy('reference')->get()
                ->map(fn (Investment $i) => ['investment' => $i, 'free' => $cycles->unallocated($i, $cycle)])
                ->filter(fn ($row) => $row['free'] > 0 || $cycle->allocations->contains('investment_id', $row['investment']->id))
            : collect();

        return view('admin.trading.cycle', [
            'cycle' => $cycle,
            'budget' => $cycle->budget(),
            'committed' => $cycle->committed(),
            'allocatable' => $allocatable,
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'buyers' => Buyer::where('is_active', true)->orderBy('name')->get(),
            'categories' => CycleExpense::CATEGORIES,
            'threshold' => (int) config('afisab.purchase_director_threshold'),
        ]);
    }

    public function store(Request $request, CycleService $cycles): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'commodity_id' => ['required', 'exists:commodities,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $cycle = $cycles->open($data['name'], Commodity::findOrFail($data['commodity_id']), $data['notes'] ?? null, $request->user());

        return redirect()->route('admin.trading.cycles.show', $cycle)->with('status', 'Trading cycle opened. Allocate investor capital next.');
    }

    public function allocate(Request $request, TradeCycle $cycle, CycleService $cycles): RedirectResponse
    {
        $data = $request->validate(['investment_id' => ['required', 'exists:investments,id'], 'amount' => ['required', 'string', 'max:20']]);

        return $this->attempt(
            fn () => $cycles->allocate($cycle, Investment::findOrFail($data['investment_id']), Money::fromNaira($data['amount']), $request->user()),
            'Capital allocated.',
        );
    }

    public function deallocate(Request $request, CycleAllocation $allocation, CycleService $cycles): RedirectResponse
    {
        return $this->attempt(fn () => $cycles->removeAllocation($allocation, $request->user()), 'Allocation removed.');
    }

    public function cancel(Request $request, TradeCycle $cycle, CycleService $cycles): RedirectResponse
    {
        return $this->attempt(fn () => $cycles->cancel($cycle, $request->user()), 'Cycle cancelled.');
    }

    public function close(Request $request, TradeCycle $cycle, CycleService $cycles): RedirectResponse
    {
        return $this->attempt(fn () => $cycles->close($cycle, $request->user()), 'Cycle closed. The result will be included in the next monthly profit distribution.');
    }
}
