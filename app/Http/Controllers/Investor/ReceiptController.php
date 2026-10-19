<?php

namespace App\Http\Controllers\Investor;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Services\Policy\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** An investor's own receipt: download it or email themselves a copy. */
class ReceiptController extends Controller
{
    public function download(Request $request, Investment $investment, ReceiptService $receipts): StreamedResponse|RedirectResponse
    {
        abort_unless($investment->user_id === $request->user()->id, 404);

        $receipt = $investment->currentReceipt();
        if (! $receipt) {
            return back()->withErrors(['receipt' => 'Your receipt is still being prepared. Please try again in a few minutes, or use "Email me a copy".']);
        }
        activity()->performedOn($investment)->causedBy($request->user())->withProperties(['receipt' => $receipt->receipt_number])->log('Receipt downloaded');

        return $receipts->download($receipt);
    }

    public function email(Request $request, Investment $investment, ReceiptService $receipts): RedirectResponse
    {
        abort_unless($investment->user_id === $request->user()->id, 404);

        $limit = (int) config('afisab.policy.investor_copies_per_day');
        $sentToday = Activity::where('description', 'Receipt emailed')->where('causer_id', $request->user()->id)
            ->where('subject_type', $investment->getMorphClass())->where('subject_id', $investment->id)->where('created_at', '>=', now()->subDay())->count();
        if ($sentToday >= $limit) {
            return back()->withErrors(['receipt' => "You have already asked for {$limit} copies today. Please try again tomorrow, or download it instead."]);
        }

        try {
            $receipts->send($investment, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return back()->with('status', 'A copy of your receipt has been emailed to '.$request->user()->email.'.');
    }
}
