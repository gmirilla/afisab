<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvestmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\CapitalPayout;
use App\Models\Investment;
use App\Services\Treasury\CapitalPayoutService;
use App\Services\Treasury\TreasuryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CapitalPayoutController extends Controller
{
    public function index(TreasuryService $treasury): View
    {
        $open = CapitalPayout::with(['investment.user.investorProfile', 'preparer', 'approver'])->orderByDesc('id')->limit(100)->get();

        return view('admin.payouts', [
            'eligible' => Investment::with('user')->where('status', InvestmentStatus::Matured)
                ->whereDoesntHave('capitalPayouts', fn ($q) => $q->where('status', '!=', CapitalPayout::REJECTED))
                ->orderBy('maturity_date')->get(),
            'payouts' => $open,
            'overview' => $treasury->overview(),
        ]);
    }

    public function prepare(Request $request, Investment $investment, CapitalPayoutService $payouts): RedirectResponse
    {
        return $this->attempt(fn () => $payouts->prepare($investment, $request->user()), 'Payout prepared and sent for approval.');
    }

    public function approve(Request $request, CapitalPayout $payout, CapitalPayoutService $payouts): RedirectResponse
    {
        return $this->attempt(fn () => $payouts->approve($payout, $request->user()), 'Payout approved.');
    }

    public function reject(Request $request, CapitalPayout $payout, CapitalPayoutService $payouts): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:255']]);

        return $this->attempt(fn () => $payouts->reject($payout, $request->user(), $data['note']), 'Payout rejected.');
    }

    public function pay(Request $request, CapitalPayout $payout, CapitalPayoutService $payouts): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:64']]);

        return $this->attempt(fn () => $payouts->markPaid($payout, $data['reference'], $request->user()), 'Marked as paid. The investment is closed.');
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['payout' => $e->getMessage()]);
        }

        return back()->with('status', $success);
    }
}
