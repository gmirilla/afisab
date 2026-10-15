<?php

namespace App\Services\Payments;

use App\Exceptions\BusinessRuleException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PaystackClient
{
    private function http(): PendingRequest
    {
        $secret = config('services.paystack.secret_key');
        if (! $secret) {
            throw new BusinessRuleException('Online payment is not available right now. Please use bank transfer.');
        }

        return Http::baseUrl(config('services.paystack.base_url'))
            ->withToken($secret)
            ->acceptJson()
            ->timeout(20);
    }

    /** @return string authorization URL to redirect the investor to */
    public function initialize(string $email, int $amountKobo, string $reference, string $callbackUrl): string
    {
        $response = $this->http()->post('/transaction/initialize', [
            'email' => $email,
            'amount' => $amountKobo,
            'reference' => $reference,
            'currency' => 'NGN',
            'callback_url' => $callbackUrl,
        ]);

        if (! $response->successful() || ! $response->json('status')) {
            throw new BusinessRuleException('Could not start the online payment. Please try again or use bank transfer.');
        }

        return $response->json('data.authorization_url');
    }

    /** @return array{paid: bool, amount: int, currency: string, gateway_reference: ?string} */
    public function verify(string $reference): array
    {
        $response = $this->http()->get('/transaction/verify/'.rawurlencode($reference));

        return [
            'paid' => $response->successful() && $response->json('data.status') === 'success',
            'amount' => (int) $response->json('data.amount', 0),
            'currency' => (string) $response->json('data.currency', ''),
            'gateway_reference' => $response->json('data.id') ? (string) $response->json('data.id') : null,
        ];
    }

    public function validSignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '' || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }
}
