<?php

namespace App\Notifications;

use App\Models\DistributionLine;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfitPaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DistributionLine $line) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $line = $this->line->loadMissing(['run', 'investment']);

        return (new MailMessage)
            ->subject('Your AFISAB profit share has been paid')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your profit share of '.Money::format($line->investor_share)." for {$line->run->label()} on investment {$line->investment->reference} has been paid.")
            ->line("Payment reference: {$line->payment_reference}")
            ->action('View your investment', route('investor.investments.show', $line->investment));
    }
}
