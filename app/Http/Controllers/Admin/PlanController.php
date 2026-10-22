<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestmentPlan;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans', ['plans' => InvestmentPlan::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new InvestmentPlan);
    }

    public function update(Request $request, InvestmentPlan $plan): RedirectResponse
    {
        return $this->save($request, $plan);
    }

    /** Swap a plan with its neighbour, then renumber every plan 1..n so positions are always unique and gap-free. */
    public function move(Request $request, InvestmentPlan $plan): RedirectResponse
    {
        $data = $request->validate(['direction' => ['required', 'in:up,down']]);

        DB::transaction(function () use ($plan, $data, $request) {
            $ids = InvestmentPlan::orderBy('sort_order')->orderBy('id')->lockForUpdate()->pluck('id')->all();
            $at = array_search($plan->id, $ids, true);
            $to = $data['direction'] === 'up' ? $at - 1 : $at + 1;
            if ($at === false || ! isset($ids[$to])) {
                return; // already first or last
            }
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            foreach ($ids as $i => $id) {
                InvestmentPlan::whereKey($id)->update(['sort_order' => $i + 1]);
            }
            activity()->performedOn($plan)->causedBy($request->user())->withProperties(['direction' => $data['direction'], 'position' => $to + 1])->log('Plan order changed');
        });

        return back()->with('status', 'The order of the plans was updated.');
    }

    private function save(Request $request, InvestmentPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'duration_months' => ['required', 'integer', 'between:1,60'],
            'minimum_amount' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
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
        ]);
        if (! $plan->exists) {
            $plan->sort_order = (int) InvestmentPlan::max('sort_order') + 1; // new plans go to the end
            $plan->slug = Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        }
        $plan->save();
        activity()->performedOn($plan)->causedBy($request->user())->log('Plan saved');

        return back()->with('status', 'Plan saved.');
    }
}
