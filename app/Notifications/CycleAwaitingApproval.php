<?php

namespace App\Notifications;

use App\Models\TradeCycle;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/** Staff-facing: a trading cycle has been proposed and needs a Director's approval. */
class CycleAwaitingApproval extends AfisabNotification
{
    public $afterCommit = true;

    public function __construct(public TradeCycle $cycle) {}

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
