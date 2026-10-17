<?php

namespace App\Notifications;

use App\Models\CapitalPayout;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

class CapitalPaid extends AfisabNotification
{
    public $afterCommit = true;

    public function __construct(public CapitalPayout $payout) {}

    public function toMail(object $notifiable): MailMessage
    {
        $payout = $this->payout->loadMissing('investment');

        return (new MailMessage)
            ->subject('Your AFISAB capital has been returned')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your capital of '.Money::format($payout->amount)." for investment {$payout->investment->reference} has been paid to your bank account.")
            ->line("Payment reference: {$payout->payment_reference}")
            ->line('Thank you for investing with AFISAB.')
            ->action('View your investment', route('investor.investments.show', $payout->investment));
    }
}
