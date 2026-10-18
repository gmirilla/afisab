<?php

namespace App\Http\Controllers\Investor;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\WithdrawalRequest;
use App\Services\Treasury\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** An investor's notice of early withdrawal (agreement clause 9.2) and its cancellation. */
class WithdrawalController extends Controller
{
    public function store(Request $request, Investment $investment, WithdrawalService $withdrawals): RedirectResponse
    {
        abort_unless($investment->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'understand' => ['accepted'],
        ], ['understand.accepted' => 'Please confirm that you understand how early withdrawal works.']);

        try {
            $notice = $withdrawals->request($investment, $request->user(), $data['reason'] ?? null);
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }

        return back()->with('status', 'Your notice was received. Your notice period runs until '.$notice->notice_ends_on->format('d F Y').'. We have emailed you the details.');
    }

    public function cancel(Request $request, WithdrawalRequest $withdrawal, WithdrawalService $withdrawals): RedirectResponse
    {
        abort_unless($withdrawal->investment->user_id === $request->user()->id, 404);

        try {
            $withdrawals->cancel($withdrawal, $request->user(), 'Cancelled by the investor');
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }

        return back()->with('status', 'Your notice of early withdrawal was cancelled. Your investment continues as before.');
    }
}
