<?php

namespace App\Http\Controllers\Investor;

use App\Enums\ProfitFrequency;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Services\Policy\PolicyDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** An investor's own agreement: read it before subscribing, then download it or email themselves a copy. */
class PolicyController extends Controller
{
    /** The agreement's wording for a plan, before anything is signed. */
    public function preview(Request $request, PolicyDocumentService $documents): View
    {
        $data = $request->validate(['plan' => ['required', 'exists:investment_plans,id'], 'frequency' => ['nullable'], 'amount' => ['nullable', 'string', 'max:20']]);
        $plan = InvestmentPlan::findOrFail($data['plan']);
        $amount = null;
        try {
            $amount = ! empty($data['amount']) ? \App\Support\Money::fromNaira($data['amount']) : null;
        } catch (\InvalidArgumentException) {
        }

        $context = $documents->previewContext($request->user(), $plan, ProfitFrequency::tryFrom((string) ($data['frequency'] ?? '')), $amount);

        return view('investor.agreement', ['c' => $context, 'sections' => $documents->sections($context), 'schedule' => $documents->schedule($context), 'plan' => $plan]);
    }

    public function download(Request $request, Investment $investment, PolicyDocumentService $documents): StreamedResponse|RedirectResponse
    {
        abort_unless($investment->user_id === $request->user()->id, 404);

        $document = $investment->currentPolicyDocument();
        if (! $document) {
            return back()->withErrors(['policy' => 'Your agreement is still being prepared. Please try again in a few minutes, or use "Email me a copy".']);
        }
        activity()->performedOn($investment)->causedBy($request->user())->withProperties(['version' => $document->version])->log('Policy document downloaded');

        return $documents->download($document);
    }

    public function email(Request $request, Investment $investment, PolicyDocumentService $documents): RedirectResponse
    {
        abort_unless($investment->user_id === $request->user()->id, 404);

        $limit = (int) config('afisab.policy.investor_copies_per_day');
        $sentToday = Activity::where('description', 'Policy document emailed')->where('causer_id', $request->user()->id)
            ->where('subject_type', $investment->getMorphClass())->where('subject_id', $investment->id)->where('created_at', '>=', now()->subDay())->count();
        if ($sentToday >= $limit) {
            return back()->withErrors(['policy' => "You have already asked for {$limit} copies today. Please try again tomorrow, or download it instead."]);
        }

        try {
            $documents->send($investment, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['policy' => $e->getMessage()]);
        }

        return back()->with('status', 'A copy has been emailed to '.$request->user()->email.'.');
    }
}
