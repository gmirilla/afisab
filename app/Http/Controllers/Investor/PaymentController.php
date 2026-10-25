<?php

namespace App\Http\Controllers\Investor;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function paystack(Request $request, Investment $investment, PaymentService $payments): RedirectResponse
    {
        abort_unless($investment->user_id === $request->user()->id, 404);

        try {
            return redirect()->away($payments->startPaystack($investment));
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }
    }

    /** Browser return from Paystack: verify with the gateway, never trust the query string. */
    public function callback(Request $request, PaymentService $payments): RedirectResponse
    {
        $reference = (string) $request->query('reference', $request->query('trxref', ''));
        $payment = Payment::where('reference', $reference)->with('investment')->first();
        abort_unless($payment && $payment->investment->user_id === $request->user()->id, 404);

        try {
            $payments->settlePaystack($reference);
        } catch (BusinessRuleException $e) {
            return redirect()->route('investor.investments.show', $payment->investment)->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->route('investor.investments.show', $payment->investment)
            ->with('status', 'Payment received. If your investment is not active yet it will be shortly.');
    }

    public function transfer(Request $request, Investment $investment, PaymentService $payments): RedirectResponse
    {
        abort_unless($investment->user_id === $request->user()->id, 404);

        $request->validate(['proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096']], ['proof.required' => 'Please attach your transfer receipt.']);

        try {
            $payments->submitTransfer($investment, $request->file('proof'));
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['proof' => $e->getMessage()]);
        }

        return redirect()->route('investor.investments.show', $investment)
            ->with('status', 'Receipt uploaded. Accounts will confirm your payment shortly.');
    }

    public function proof(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        abort_unless($payment->investment->user_id === $request->user()->id, 404);

        $request->validate(['proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096']]);

        try {
            $payments->submitProof($payment, $request->file('proof'));
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['proof' => $e->getMessage()]);
        }

        return back()->with('status', 'Receipt uploaded. Accounts will confirm your payment shortly.');
    }
}
