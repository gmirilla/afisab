<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Services\Treasury\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Staff view of early-withdrawal notices: what each is waiting for, and a button to mature it once nothing is. */
class WithdrawalController extends Controller
{
    public function index(WithdrawalService $withdrawals): View
    {
        $all = WithdrawalRequest::with(['investment.user'])->orderByRaw("case status when 'requested' then 0 when 'matured' then 1 else 2 end")->orderBy('notice_ends_on')->orderByDesc('id')->limit(200)->get();

        return view('admin.withdrawals', [
            'open' => $all->where('status', WithdrawalRequest::REQUESTED)->map(fn ($r) => ['request' => $r, 'blockers' => $withdrawals->blockers($r)]),
            'others' => $all->where('status', '!=', WithdrawalRequest::REQUESTED),
        ]);
    }

    public function process(Request $request, WithdrawalRequest $withdrawal, WithdrawalService $withdrawals): RedirectResponse
    {
        try {
            $withdrawals->process($withdrawal, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }

        return back()->with('status', 'The investment has matured early. Prepare its capital payout on the Capital payouts page.');
    }

    public function cancel(Request $request, WithdrawalRequest $withdrawal, WithdrawalService $withdrawals): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:200']]);

        try {
            $withdrawals->cancel($withdrawal, $request->user(), $data['note']);
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }

        return back()->with('status', 'The notice was cancelled.');
    }
}
