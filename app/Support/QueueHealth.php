<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Emails are queued, so they only leave if the host's queue cron runs and the mail server accepts them.
 * This looks for the two ways that goes wrong quietly: messages that have been waiting too long (the queue
 * worker is not running) and messages that gave up after their retries (the mail server rejected them).
 */
class QueueHealth
{
    /** A queued email older than this means nothing is picking it up (the worker runs every minute). */
    public const STUCK_AFTER_MINUTES = 10;

    public static function failedCount(): int
    {
        try {
            return Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /** Minutes the oldest waiting job has been waiting, or null when nothing is waiting or the queue is not the database. */
    public static function oldestWaitingMinutes(): ?int
    {
        if (config('queue.default') !== 'database') {
            return null;
        }
        try {
            if (! Schema::hasTable('jobs')) {
                return null;
            }
            $oldest = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
        } catch (Throwable) {
            return null;
        }

        return $oldest === null ? null : max(0, intdiv(now()->timestamp - (int) $oldest, 60));
    }

    /** A sentence for staff, or null when all is well. */
    public static function warning(): ?string
    {
        $waiting = self::oldestWaitingMinutes();
        if ($waiting !== null && $waiting >= self::STUCK_AFTER_MINUTES) {
            return "Emails are waiting to be sent but nothing is sending them (the oldest has waited {$waiting} minutes). Check that the queue cron job from deploy/crontab.txt is running.";
        }

        $failed = self::failedCount();
        if ($failed > 0) {
            return "{$failed} ".($failed === 1 ? 'email' : 'emails').' could not be delivered after several tries. Check the mail settings (run php artisan afisab:test-mail you@example.com), then php artisan queue:retry all.';
        }

        return null;
    }
}
