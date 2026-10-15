<?php

namespace App\Notifications;

use App\Models\CapitalPayout;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Staff-facing: a capital payout is waiting for approval. */
class CapitalPayoutReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CapitalPayout $payout) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payout = $this->payout->loadMissing('investment.user');

        return (new MailMessage)
            ->subject('Capital payout awaiting approval')
            ->greeting("Hello {$notifiable->name},")
            ->line('A payout of '.Money::format($payout->amount)." to {$payout->investment->user->name} (investment {$payout->investment->reference}) is ready for your approval.")
            ->action('Review capital payouts', route('admin.payouts'));
    }
}
