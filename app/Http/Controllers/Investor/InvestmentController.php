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
            'plans' => InvestmentPlan::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'investment_plan_id' => ['required', 'exists:investment_plans,id'],
            'amount' => ['required', 'string', 'max:20'],
            'profit_frequency' => ['nullable', Rule::enum(ProfitFrequency::class)],
            'accept_terms' => ['accepted'],
        ], ['accept_terms.accepted' => 'Please confirm that you have read and agree to the Investment Agreement.']);

        if (! $request->user()->investorProfile?->nin) {
            return back()->withInput()->withErrors(['amount' => 'Please add your NIN (National Identification Number) on your profile before you invest.']);
        }

        try {
            $plan = InvestmentPlan::findOrFail($data['investment_plan_id']);
            $investment = $subscriptions->subscribe(
                $request->user(),
                $plan,
                Money::fromNaira($data['amount']),
                ProfitFrequency::tryFrom($data['profit_frequency'] ?? '') ?? ProfitFrequency::availableFor($plan->duration_months)[0],
                ['ip' => $request->ip()],
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
