<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(): View
    {
        return view('admin.payments', [
            'payments' => Payment::with('investment.user')
                ->where('method', PaymentMethod::Transfer)
                ->where('status', PaymentStatus::AwaitingConfirmation)
                ->latest()->get(),
        ]);
    }

    public function proof(Payment $payment): StreamedResponse
    {
        abort_unless($payment->proof_path && Storage::exists($payment->proof_path), 404);

        return Storage::response($payment->proof_path);
    }

    public function confirm(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        if ($payment->status === PaymentStatus::Confirmed) {
            return back()->with('status', "Payment {$payment->reference} is already confirmed.");
        }
        if ($payment->status !== PaymentStatus::AwaitingConfirmation) {
            return back()->withErrors(['payment' => $payment->status === PaymentStatus::Pending
                ? 'The investor has not submitted a receipt for this payment yet.'
                : 'This payment was '.strtolower($payment->status->label()).' and cannot be confirmed.']);
        }

        $data = $request->validate(
            ['received_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$payment->investment->created_at->toDateString()]],
            ['received_on.required' => 'Please enter the date the money was received.', 'received_on.before_or_equal' => 'The date the money was received cannot be in the future.', 'received_on.after_or_equal' => 'The money cannot have been received before the investor subscribed.'],
        );

        try {
            $payments->confirm($payment, $request->user(), null, null, \Illuminate\Support\Carbon::parse($data['received_on']));
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('status', "Payment {$payment->reference} confirmed; investment activated.");
    }

    public function reject(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        try {
            $payments->reject($payment, $request->user(), $data['note']);
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('status', 'Payment rejected.');
    }
}
