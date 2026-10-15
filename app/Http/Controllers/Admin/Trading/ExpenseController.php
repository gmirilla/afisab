<?php

namespace App\Http\Controllers\Admin\Trading;

use App\Http\Controllers\Concerns\AttemptsActions;
use App\Http\Controllers\Controller;
use App\Models\CycleExpense;
use App\Models\TradeCycle;
use App\Services\Trading\ExpenseService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    use AttemptsActions;

    public function propose(Request $request, TradeCycle $cycle, ExpenseService $expenses): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(CycleExpense::CATEGORIES)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string', 'max:20'],
        ]);

        return $this->attempt(
            fn () => $expenses->propose($cycle, $data['category'], $data['description'], Money::fromNaira($data['amount']), $request->user()),
            'Expense proposed.',
        );
    }

    public function pay(Request $request, CycleExpense $expense, ExpenseService $expenses): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:64']]);

        return $this->attempt(fn () => $expenses->pay($expense, $data['reference'], $request->user()), 'Expense paid.');
    }

    public function reject(Request $request, CycleExpense $expense, ExpenseService $expenses): RedirectResponse
    {
        return $this->attempt(fn () => $expenses->reject($expense, $request->user()), 'Expense rejected.');
    }
}
