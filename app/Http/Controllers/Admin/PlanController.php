<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestmentPlan;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans', ['plans' => InvestmentPlan::orderBy('sort_order')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new InvestmentPlan);
    }

    public function update(Request $request, InvestmentPlan $plan): RedirectResponse
    {
        return $this->save($request, $plan);
    }

    private function save(Request $request, InvestmentPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'duration_months' => ['required', 'integer', 'between:1,60'],
            'minimum_amount' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,1000'],
        ]);

        try {
            $minimum = Money::fromNaira($data['minimum_amount']);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['minimum_amount' => 'Enter a valid amount in naira.']);
        }

        if ($minimum < (int) config('afisab.minimum_investment')) {
            return back()->withInput()->withErrors(['minimum_amount' => 'Cannot be below the company minimum of ₦500,000.']);
        }

        $plan->fill([
            'name' => $data['name'],
            'duration_months' => $data['duration_months'],
            'minimum_amount' => $minimum,
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
        if (! $plan->exists) {
            $plan->slug = Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        }
        $plan->save();
        activity()->performedOn($plan)->causedBy($request->user())->log('Plan saved');

        return back()->with('status', 'Plan saved.');
    }
}
