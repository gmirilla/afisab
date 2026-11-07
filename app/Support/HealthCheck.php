<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Is the application doing the things it silently depends on? The review found emails stuck in the queue for a week with
 * nobody told. A staff member only learns of that by opening the dashboard; this is what an uptime monitor (or cron) can
 * watch instead, so someone is told within minutes. Used by /health and `php artisan afisab:health`.
 */
class HealthCheck
{
    /** @return array{status: 'ok'|'degraded', checks: array<string, array{ok: bool, detail: string}>} */
    public static function run(): array
    {
        $checks = [
            'database' => self::database(),
            'scheduler' => self::scheduler(),
            'queue' => self::queue(),
            'mail' => self::mail(),
        ];

        return ['status' => collect($checks)->every(fn ($c) => $c['ok']) ? 'ok' : 'degraded', 'checks' => $checks];
    }

    private static function database(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true, 'detail' => 'reachable'];
        } catch (Throwable) {
            return ['ok' => false, 'detail' => 'the database cannot be reached'];
        }
    }

    /** The every-minute cron job: it also runs the 27th profit calculation, maturities and reminders. */
    private static function scheduler(): array
    {
        return match (SchedulerHealth::status()) {
            'ok' => ['ok' => true, 'detail' => 'running'],
            'stale' => ['ok' => false, 'detail' => 'the every-minute cron job has stopped (no heartbeat for over '.SchedulerHealth::STALE_AFTER_MINUTES.' minutes)'],
            default => app()->environment('production')
                ? ['ok' => false, 'detail' => 'the every-minute cron job has never reported in']
                : ['ok' => true, 'detail' => 'no heartbeat yet (fine outside production)'],
        };
    }

    /** Emails, receipts and agreements are sent by the queue worker. */
    private static function queue(): array
    {
        if (config('queue.default') === 'sync') {
            return ['ok' => true, 'detail' => 'sync (sent immediately, no worker needed)'];
        }
        if (QueueMonitor::isPaused()) {
            return ['ok' => false, 'detail' => 'the queue is paused: emails and documents are waiting'];
        }
        $waiting = QueueHealth::oldestWaitingMinutes();
        if ($waiting !== null && $waiting >= QueueHealth::STUCK_AFTER_MINUTES) {
            return ['ok' => false, 'detail' => "emails or documents have been waiting {$waiting} minutes and nothing is sending them (the queue cron job is not running)"];
        }
        $failed = QueueHealth::failedCount();
        if ($failed > 0) {
            return ['ok' => false, 'detail' => "{$failed} queued job(s) failed after their retries (see the Job queue page)"];
        }

        return ['ok' => true, 'detail' => 'sending'];
    }

    private static function mail(): array
    {
        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['log', 'array'], true) && app()->environment('production')) {
            return ['ok' => false, 'detail' => "the mailer is '{$mailer}': nothing is really being sent"];
        }

        return ['ok' => true, 'detail' => "mailer: {$mailer}"];
    }
}
