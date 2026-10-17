<?php

namespace App\Notifications;

use App\Models\TradeCycle;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Staff-facing: a trading cycle has been proposed and needs a Director's approval. */
class CycleAwaitingApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TradeCycle $cycle) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cycle = $this->cycle->loadMissing('commodity', 'proposer');

        return (new MailMessage)
            ->subject('Trading cycle awaiting approval')
            ->greeting("Hello {$notifiable->name},")
            ->line("{$cycle->proposer?->name} proposed the cycle \"{$cycle->name}\" ({$cycle->commodity->name}) with ".Money::format($cycle->budget()).' of investor capital. It cannot trade until you approve it.')
            ->action('Review the cycle', route('admin.trading.cycles.show', $cycle));
    }
}
