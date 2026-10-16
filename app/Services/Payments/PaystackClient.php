<?php

namespace App\Services\Payments;

use App\Exceptions\BusinessRuleException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class PaystackClient
{
    public function __construct(private PaystackSettings $settings) {}

    private function http(): PendingRequest
    {
        $secret = $this->settings->secret();
        if ($secret === '') {
            throw new BusinessRuleException('Online payment is not available right now. Please use bank transfer.');
        }

        return Http::baseUrl($this->settings->baseUrl())
            ->withToken($secret)
            ->acceptJson()
            ->timeout(20);
    }

    /** @return string authorization URL to redirect the investor to */
    public function initialize(string $email, int $amountKobo, string $reference, string $callbackUrl): string
    {
        if (! $this->settings->enabled()) {
            throw new BusinessRuleException('Online payment is not available right now. Please use bank transfer.');
        }

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

    /**
     * Ask Paystack something harmless that needs a valid secret key, to prove the saved key works.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        try {
            $response = $this->http()->get('/transaction', ['perPage' => 1]);
        } catch (BusinessRuleException $e) {
            return ['ok' => false, 'message' => 'No secret key is set.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach Paystack ('.class_basename($e).').'];
        }

        if ($response->status() === 401) {
            return ['ok' => false, 'message' => 'Paystack rejected the secret key.'];
        }
        if (! $response->successful() || ! $response->json('status')) {
            return ['ok' => false, 'message' => 'Paystack answered with an error (HTTP '.$response->status().').'];
        }

        return ['ok' => true, 'message' => 'Connected to Paystack ('.($this->settings->mode() ?? 'unknown').' mode).'];
    }

    public function validSignature(string $rawBody, ?string $signature): bool
    {
        $secret = $this->settings->secret();
        if ($secret === '' || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }
}
