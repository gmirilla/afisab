<?php

namespace App\Notifications;

use App\Enums\ProfitFrequency;
use App\Models\Investment;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

class MaturityApproaching extends AfisabNotification
{
    public $afterCommit = true;

    public function __construct(public Investment $investment) {}

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->investment;

        $mail = (new MailMessage)
            ->subject('Your AFISAB investment matures soon')
            ->greeting("Hello {$notifiable->name},")
            ->line("Investment {$i->reference} (".Money::format($i->amount).") matures on {$i->maturity_date->format('d M Y')}.");

        if ($i->profit_frequency !== ProfitFrequency::Monthly) {
            $mail->line("Because your profit payment frequency is {$i->profit_frequency->label()}, any profit not yet paid to you is settled in the distribution for the month of maturity.");
        }

        return $mail
            ->line('Our team will be in touch about settlement. Please make sure your bank details are up to date.')
            ->action('View your investment', route('investor.investments.show', $i));
    }
}
