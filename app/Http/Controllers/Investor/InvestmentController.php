<?php

namespace App\Http\Controllers\Investor;

use App\Enums\InvestmentStatus;
use App\Enums\ProfitFrequency;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\DistributionLine;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Services\Performance\DistributionService;
use App\Services\Performance\PerformanceService;
use App\Services\Performance\ProfitSchedule;
use App\Services\SubscriptionService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class InvestmentController extends Controller
{
    public function dashboard(Request $request, PerformanceService $performance): View
    {
        $investments = $request->user()->investments()->with('plan')->latest()->get();
        $active = $investments->where('status', InvestmentStatus::Active);
        $positions = $investments->mapWithKeys(fn ($i) => [$i->id => $performance->position($i)]);

        return view('investor.dashboard', [
            'investments' => $investments,
            'positions' => $positions,
            'next' => DistributionService::nextSchedule(),
            'nextRuns' => $investments->mapWithKeys(fn ($i) => [$i->id => ProfitSchedule::nextRun($i)]),
            'totalValue' => $investments->whereIn('status', [InvestmentStatus::Active, InvestmentStatus::Matured])->sum(fn ($i) => $positions[$i->id]['current_value']),
            'totalInvested' => $active->sum('amount'),
            'activeCount' => $active->count(),
            'profile' => $request->user()->investorProfile,
        ]);
    }

    public function plans(): View
    {
        return view('investor.plans', [
            'plans' => InvestmentPlan::where('is_active', true)->orderBy('sort_order')->get(),
            'frequencies' => ProfitFrequency::cases(),
        ]);
    }

    public function store(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'investment_plan_id' => ['required', 'exists:investment_plans,id'],
            'amount' => ['required', 'string', 'max:20'],
            'profit_frequency' => ['nullable', Rule::enum(ProfitFrequency::class)],
        ]);

        try {
            $investment = $subscriptions->subscribe(
                $request->user(),
                InvestmentPlan::findOrFail($data['investment_plan_id']),
                Money::fromNaira($data['amount']),
                ProfitFrequency::tryFrom($data['profit_frequency'] ?? '') ?? ProfitFrequency::Monthly,
            );
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'Enter a valid amount in naira.']);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('investor.investments.show', $investment)
            ->with('status', 'Subscription created. Complete payment to activate it.');
    }

    public function show(Request $request, Investment $investment, PerformanceService $performance): View
    {
        abort_unless($investment->user_id === $request->user()->id, 404);

        return view('investor.investment', [
            'position' => $performance->position($investment),
            'entries' => $investment->performanceEntries()->with('run')->get(),
            'distributions' => DistributionLine::with('run')->where('investment_id', $investment->id)
                ->whereIn('status', [DistributionLine::PAYABLE, DistributionLine::PAID])->latest()->get(),
            'next' => ProfitSchedule::nextRun($investment) ?? DistributionService::nextSchedule(),
            'investment' => $investment->load(['plan', 'payments']),
        ]);
    }

    public function cancel(Request $request, Investment $investment, SubscriptionService $subscriptions): RedirectResponse
    {
        abort_unless($investment->user_id === $request->user()->id, 404);

        try {
            $subscriptions->cancel($investment);
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }

        return redirect()->route('investor.dashboard')->with('status', 'Subscription cancelled.');
    }
}
