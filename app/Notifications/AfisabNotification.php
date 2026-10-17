<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base for every email this system sends.
 *
 *  - Queued, so a slow or broken mail server never delays or fails the action that triggered it.
 *  - After commit: nothing is sent about money that moved in a transaction which then rolled back.
 *    (Queueable declares the flag, so each concrete notification sets `public $afterCommit = true;`.)
 *  - Retried with a growing delay when the mail server is briefly unavailable; a final failure is logged and
 *    appears under failed jobs (the back office warns about those).
 */
abstract class AfisabNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @return array<int, int> seconds to wait before each retry */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function failed(Throwable $e): void
    {
        Log::error('Notification could not be delivered: '.static::class.': '.$e->getMessage());
    }
}
