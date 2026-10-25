<?php

namespace App\Services\Payments;

use App\Exceptions\BusinessRuleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Throwable;

class PaystackClient
{
    public const LAST_FAILURE = 'afisab.paystack.last_failure';

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

        try {
            $response = $this->http()->post('/transaction/initialize', [
                'email' => $email,
                'amount' => $amountKobo,
                'reference' => $reference,
                'currency' => 'NGN',
                'callback_url' => $callbackUrl,
            ]);
        } catch (ConnectionException $e) {
            $this->recordFailure(null, 'Could not reach Paystack: '.Str::limit($e->getMessage(), 200), null, $reference);
            throw new BusinessRuleException('Could not start the online payment. Please try again or use bank transfer.');
        }

        if (! $response->successful() || ! $response->json('status')) {
            $this->recordFailure($response->status(), (string) $response->json('message', 'No message returned'), $response->json('code'), $reference);
            throw new BusinessRuleException('Could not start the online payment. Please try again or use bank transfer.');
        }

        return $response->json('data.authorization_url');
    }

    /**
     * Investors only ever see a generic message, so the real reason is kept for the super-admin: in the log, and as the
     * "last failure" card on the Paystack page. Nothing secret goes in (no key, no payload, no email address).
     */
    private function recordFailure(?int $status, string $message, ?string $code, string $reference): void
    {
        Log::warning('Paystack could not start a payment', ['http' => $status, 'message' => $message, 'code' => $code, 'reference' => $reference]);
        Cache::put(self::LAST_FAILURE, ['at' => now()->timestamp, 'http' => $status, 'message' => $message, 'code' => $code, 'hint' => self::hint($status, $message, $code)], now()->addDays(14));
    }

    /** What the super-admin should check, for the failures seen most often. */
    public static function hint(?int $status, string $message, ?string $code): string
    {
        $text = strtolower($message.' '.$code);

        return match (true) {
            $status === null => 'The server could not connect to Paystack. Check the host\'s internet access, firewall and SSL certificates (on Windows/XAMPP, curl.cainfo in php.ini).',
            $status === 401, str_contains($text, 'secret_key_invalid'), str_contains($text, 'invalid key') => 'Paystack rejected the secret key. Re-enter it on this page (Paystack dashboard, Settings, API Keys), making sure it is the secret key (sk_...), not the public key, and that test/live mode matches your account.',
            str_contains($text, 'currency') => 'Paystack does not allow NGN on this account. Check the account\'s business country and enabled currencies.',
            str_contains($text, 'email') => 'Paystack rejected the investor\'s email address. Use a real, well-formed address (some test domains are refused).',
            str_contains($text, 'amount') => 'Paystack rejected the amount (below its minimum or above the account\'s transaction limit).',
            str_contains($text, 'reference') => 'Paystack says this payment reference was already used. Try again.',
            $status === 403 => 'Paystack refused the request. The account may not be activated for this mode, or the server IP may be blocked.',
            $status >= 500 => 'Paystack had a problem on its side. Try again shortly.',
            default => 'See the message above and the Paystack dashboard (Logs). The laravel.log file has the same details.',
        };
    }

    /** @return array{at: int, http: ?int, message: string, code: ?string, hint: string}|null */
    public static function lastFailure(): ?array
    {
        return Cache::get(self::LAST_FAILURE);
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
