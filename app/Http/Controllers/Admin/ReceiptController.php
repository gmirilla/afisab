<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\Receipt;
use App\Services\Policy\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Staff view of an investor's receipts: reprint, resend, re-issue a corrected version, or void. */
class ReceiptController extends Controller
{
    public function download(Request $request, Receipt $receipt, ReceiptService $receipts): StreamedResponse
    {
        activity()->performedOn($receipt->investment)->causedBy($request->user())->withProperties(['receipt' => $receipt->receipt_number, 'version' => $receipt->version])->log('Receipt downloaded by staff');

        return $receipts->download($receipt);
    }

    public function send(Request $request, Investment $investment, ReceiptService $receipts): RedirectResponse
    {
        try {
            $receipts->send($investment, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return back()->with('status', 'The receipt has been emailed to '.$investment->user->email.'.');
    }

    /** First issue (when the automatic one did not happen) or a corrected version that supersedes the current one. */
    public function reissue(Request $request, Investment $investment, ReceiptService $receipts): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:200'], 'send_copy' => ['nullable', 'boolean']]);

        try {
            $receipt = $receipts->issueForInvestment($investment, $request->user(), $data['reason'], force: true);
            if ($request->boolean('send_copy')) {
                $investment->user->notify(new \App\Notifications\ReceiptDelivered($receipt));
            }
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return back()->with('status', "Receipt {$receipt->receipt_number} version {$receipt->version} issued".($request->boolean('send_copy') ? ' and emailed to the investor.' : '.'));
    }

    public function void(Request $request, Receipt $receipt, ReceiptService $receipts): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:200']]);

        try {
            $receipts->void($receipt, $request->user(), $data['reason']);
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return back()->with('status', "Receipt {$receipt->receipt_number} has been voided.");
    }
}
