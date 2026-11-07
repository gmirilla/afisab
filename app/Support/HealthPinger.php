<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells a "dead man's switch" service (Healthchecks.io and similar) that the application is alive and well. It is the
 * other half of /health: that URL lets a monitor ask the site, this lets the site report in, so the service alerts when
 * the reports STOP, which is what happens when the server or the cron job dies. A failed check goes to the /fail address
 * with the failing parts named. The ping URL is a secret: it is never logged or shown. Nothing here can break the scheduler.
 */
class HealthPinger
{
    public static function enabled(): bool
    {
        return filled(config('afisab.healthcheck_ping_url'));
    }

    /** @return 'ok'|'fail'|'error'|'off' what was reported ('error': the ping itself could not be delivered) */
    public static function ping(): string
    {
        if (! self::enabled()) {
            return 'off';
        }
        $base = rtrim((string) config('afisab.healthcheck_ping_url'), '/');
        $result = HealthCheck::run();

        try {
            if ($result['status'] === 'ok') {
                Http::timeout(10)->get($base);

                return 'ok';
            }

            $failing = collect($result['checks'])->reject(fn ($c) => $c['ok'])->map(fn ($c, $name) => "{$name}: {$c['detail']}")->implode("\n");
            Http::timeout(10)->withBody($failing, 'text/plain')->post($base.'/fail');

            return 'fail';
        } catch (Throwable $e) {
            Log::warning('The health ping could not be delivered', ['error' => class_basename($e)]); // never the URL
            return 'error';
        }
    }
}
