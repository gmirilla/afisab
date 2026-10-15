<?php

namespace App\Http\Controllers\Webhooks;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PaystackClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackClient $paystack, PaymentService $payments): Response
    {
        if (! $paystack->validSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            return response('Invalid signature', 401);
        }

        if ($request->input('event') === 'charge.success') {
            try {
                // Re-verify with the gateway instead of trusting the payload.
                $payments->settlePaystack((string) $request->input('data.reference'));
            } catch (BusinessRuleException $e) {
                report($e);
            }
        }

        return response('OK', 200);
    }
}
