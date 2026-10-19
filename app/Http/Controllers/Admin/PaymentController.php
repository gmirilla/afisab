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
                ->whereIn('status', [PaymentStatus::AwaitingConfirmation, PaymentStatus::Pending])
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
        abort_unless($payment->status === PaymentStatus::AwaitingConfirmation, 422, 'No receipt has been submitted for this payment.');

        $data = $request->validate(['received_on' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:'.$payment->investment->created_at->toDateString()]]);

        try {
            $payments->confirm($payment, $request->user(), null, null, isset($data['received_on']) ? \Illuminate\Support\Carbon::parse($data['received_on']) : null);
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
