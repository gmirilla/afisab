<?php

namespace App\Services\Payments;

use App\Models\PaystackSetting;

/**
 * Resolves the Paystack configuration: the super-admin's saved settings first, the PAYSTACK_* .env values otherwise.
 *
 * "Enabled" only controls STARTING new online payments. Verification, the callback and the webhook keep working
 * whenever a secret key exists, so money already in flight is never stranded by switching the feature off.
 * Not cached: it is read a handful of times per request and a stale key after an edit would be worse.
 */
class PaystackSettings
{
    public function row(): ?PaystackSetting
    {
        return PaystackSetting::first();
    }

    public function secret(): string
    {
        $row = $this->row();

        return (string) ($row ? $row->secret_key : config('services.paystack.secret_key'));
    }

    public function publicKey(): string
    {
        $row = $this->row();

        return (string) ($row ? $row->public_key : config('services.paystack.public_key'));
    }

    public function baseUrl(): string
    {
        return (string) config('services.paystack.base_url');
    }

    public function configured(): bool
    {
        return $this->secret() !== '';
    }

    /** May investors start a new online payment? */
    public function enabled(): bool
    {
        $row = $this->row();

        return $this->configured() && ($row ? $row->enabled : true);
    }

    /** 'live', 'test' or null when no recognisable key is set. */
    public function mode(): ?string
    {
        return match (true) {
            str_starts_with($this->secret(), 'sk_live_') => 'live',
            str_starts_with($this->secret(), 'sk_test_') => 'test',
            default => null,
        };
    }

    public function source(): string
    {
        return $this->row() ? 'database' : '.env';
    }

    /** sk_live_••••abcd — enough to recognise a key, never enough to use it. */
    public static function mask(string $key): string
    {
        if ($key === '') {
            return '';
        }
        $prefix = preg_match('/^(sk|pk)_(live|test)_/', $key, $m) ? $m[0] : '';

        return $prefix.'••••'.substr($key, -4);
    }
}
