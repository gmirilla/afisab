<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\PaystackSetting;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PaystackClient;
use App\Services\Payments\PaystackSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Paystack integration management. Super-admin only (permission paystack.manage): these keys move investors' money.
 * Keys are encrypted at rest, never shown in full, never written to the audit trail, and every change needs the
 * current password.
 */
class PaystackController extends Controller
{
    public function index(PaystackSettings $settings): View
    {
        $row = $settings->row();

        return view('admin.paystack', [
            'settings' => $settings,
            'row' => $row,
            'secretMasked' => PaystackSettings::mask($settings->secret()),
            'publicMasked' => PaystackSettings::mask($settings->publicKey()),
            'lastFailure' => \App\Services\Payments\PaystackClient::lastFailure(),
            'webhookUrl' => route('webhooks.paystack'),
            'callbackUrl' => route('investor.payments.callback'),
            'production' => app()->isProduction(),
            'payments' => Payment::with('investment.user')->where('method', PaymentMethod::Paystack)->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function save(Request $request, PaystackSettings $settings): RedirectResponse
    {
        $existing = $settings->row();

        $data = $request->validate([
            'public_key' => ['nullable', 'string', 'max:200', 'regex:/^pk_(test|live)_[A-Za-z0-9]+$/'],
            'secret_key' => [$existing?->secret_key || config('services.paystack.secret_key') ? 'nullable' : 'required', 'string', 'max:200', 'regex:/^sk_(test|live)_[A-Za-z0-9]+$/'],
            'password' => ['required', 'current_password'],
        ], [
            'public_key.regex' => 'A Paystack public key looks like pk_live_… or pk_test_….',
            'secret_key.regex' => 'A Paystack secret key looks like sk_live_… or sk_test_….',
            'password.current_password' => 'Your password is incorrect.',
        ]);

        // Blank = keep what is there (the form never shows the full key, so it cannot be re-submitted).
        $secret = ($data['secret_key'] ?? '') !== '' ? $data['secret_key'] : $settings->secret();
        $public = ($data['public_key'] ?? '') !== '' ? $data['public_key'] : $settings->publicKey();

        $modes = array_filter([
            $secret !== '' ? substr($secret, 3, 4) : null,   // "live" / "test" from sk_live_… / sk_test_…
            $public !== '' ? substr($public, 3, 4) : null,
        ]);
        if (count(array_unique($modes)) > 1) {
            return back()->withInput()->withErrors(['secret_key' => 'The public and secret keys are from different modes (one test, one live). Use a matching pair.']);
        }

        $before = ['secret' => PaystackSettings::mask($settings->secret()), 'public' => PaystackSettings::mask($settings->publicKey()), 'enabled' => $settings->enabled(), 'source' => $settings->source()];

        $row = $existing ?? new PaystackSetting;
        $row->fill([
            'secret_key' => $secret,
            'public_key' => $public,
            'enabled' => $request->boolean('enabled'),
            'last_tested_at' => null, 'last_test_ok' => null, 'last_test_message' => null, // a new key is untested
            'updated_by' => $request->user()->id,
        ])->save();

        activity()->performedOn($row)->causedBy($request->user())->withProperties([
            'before' => $before,
            'after' => ['secret' => PaystackSettings::mask($secret), 'public' => PaystackSettings::mask($public), 'enabled' => $row->enabled],
        ])->log('Paystack settings changed');

        return back()->with('status', 'Paystack settings saved. Press "Test connection" to confirm the key works.');
    }

    public function test(Request $request, PaystackClient $client, PaystackSettings $settings): RedirectResponse
    {
        $result = $client->testConnection();

        if ($row = $settings->row()) {
            $row->update(['last_tested_at' => now(), 'last_test_ok' => $result['ok'], 'last_test_message' => $result['message']]);
        }
        activity()->causedBy($request->user())->withProperties(['ok' => $result['ok'], 'message' => $result['message']])->log('Paystack connection tested');

        return $result['ok']
            ? back()->with('status', $result['message'])
            : back()->withErrors(['paystack' => $result['message']]);
    }

    /** Ask Paystack about a payment that is still open and settle it if it was in fact paid (e.g. a missed webhook). */
    public function reverify(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        abort_unless($payment->method === PaymentMethod::Paystack, 404);

        if ($payment->status !== PaymentStatus::Pending) {
            return back()->withErrors(['paystack' => 'Only a pending payment can be re-checked.']);
        }

        try {
            $result = $payments->settlePaystack($payment->reference);
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['paystack' => $e->getMessage()]);
        }
        activity()->performedOn($payment->investment)->causedBy($request->user())->withProperties(['payment' => $payment->reference])->log('Paystack payment re-checked');

        return back()->with('status', $result?->status === PaymentStatus::Confirmed
            ? "Paystack confirms {$payment->reference} was paid; the investment is now active."
            : "Paystack has no successful payment for {$payment->reference} yet.");
    }
}
