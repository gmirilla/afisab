<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The 27th profit calculation, maturity reminders and queued emails all depend on the host's every-minute
 * cron job. A scheduled task writes a heartbeat; if it goes quiet the back office warns about it.
 */
class SchedulerHealth
{
    public const KEY = 'afisab.scheduler.heartbeat';

    public const STALE_AFTER_MINUTES = 15;

    public static function beat(): void
    {
        Cache::put(self::KEY, now()->timestamp, now()->addHours(6));
    }

    /** @return 'ok'|'stale'|'unknown' */
    public static function status(): string
    {
        $last = Cache::get(self::KEY);
        if ($last === null) {
            return 'unknown';
        }

        return now()->timestamp - (int) $last > self::STALE_AFTER_MINUTES * 60 ? 'stale' : 'ok';
    }

    /** A sentence for staff who rely on the scheduler, or null when all is well. */
    public static function warning(): ?string
    {
        $status = self::status();

        if ($status === 'stale') {
            return 'The scheduled-task cron job has stopped running. Profit calculations, maturity reminders and emails will not happen until it is fixed.';
        }
        if ($status === 'unknown' && app()->environment('production')) {
            return 'The scheduled-task cron job has not reported in. Check that the every-minute cron entry is set up on the server.';
        }

        return null;
    }
}
