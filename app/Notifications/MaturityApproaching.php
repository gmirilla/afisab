<?php

namespace App\Notifications;

use App\Models\Investment;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaturityApproaching extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Investment $investment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->investment;

        return (new MailMessage)
            ->subject('Your AFISAB investment matures soon')
            ->greeting("Hello {$notifiable->name},")
            ->line("Investment {$i->reference} (".Money::format($i->amount).") matures on {$i->maturity_date->format('d M Y')}.")
            ->line('Our team will be in touch about settlement. Please make sure your bank details are up to date.')
            ->action('View your investment', route('investor.investments.show', $i));
    }
}
