<?php

namespace App\Notifications;

use App\Models\Investment;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Investment $investment)
    {
        $this->afterCommit(); // don't email about a payment whose transaction rolled back
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->investment;

        return (new MailMessage)
            ->subject('Your AFISAB investment is active')
            ->greeting("Hello {$notifiable->name},")
            ->line('We have confirmed your payment of '.Money::format($i->amount)." for investment {$i->reference}.")
            ->line("It runs from {$i->start_date->format('d M Y')} to {$i->maturity_date->format('d M Y')}.")
            ->line("Your certificate number is {$i->certificate_number}.")
            ->action('View your investment and download your certificate', route('investor.investments.show', $i))
            ->line('Commodity trading involves risk. Returns are a share of actual trading profit and are not guaranteed.');
    }
}
