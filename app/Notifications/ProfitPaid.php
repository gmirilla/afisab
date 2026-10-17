<?php

namespace App\Notifications;

use App\Models\DistributionLine;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

class ProfitPaid extends AfisabNotification
{
    public $afterCommit = true;

    public function __construct(public DistributionLine $line) {}

    public function toMail(object $notifiable): MailMessage
    {
        $line = $this->line->loadMissing(['run', 'investment']);

        $mail = (new MailMessage)
            ->subject('Your AFISAB profit share has been paid')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your profit share of '.Money::format($line->investor_share)." paid in {$line->run->label()} on investment {$line->investment->reference} has been sent to your bank account.");

        if ($line->coverage()) {
            $mail->line("It is your {$line->investment->profit_frequency->label()} payment, covering trading profit from {$line->coverage()}.");
        }

        return $mail
            ->line("Payment reference: {$line->payment_reference}")
            ->action('View your investment', route('investor.investments.show', $line->investment))
            ->line('Returns are a share of actual trading profit and are not guaranteed.');
    }
}
