<?php

namespace App\Notifications;

use App\Models\Investment;
use App\Services\Performance\ProfitSchedule;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentConfirmed extends AfisabNotification
{
    public $afterCommit = true;

    public function __construct(public Investment $investment) {}

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->investment;
        $next = ProfitSchedule::nextRun($i);

        $mail = (new MailMessage)
            ->subject('Your AFISAB investment is active')
            ->greeting("Hello {$notifiable->name},")
            ->line('We have confirmed your payment of '.Money::format($i->amount)." for investment {$i->reference}.")
            ->line("It runs from {$i->start_date->format('d M Y')} to {$i->maturity_date->format('d M Y')}.")
            ->line("Your certificate number is {$i->certificate_number}.")
            ->line("You chose to be paid your profit: {$i->profit_frequency->label()}. {$i->profit_frequency->description()}");

        if ($next) {
            $mail->line('Your first profit calculation is on '.$next['cutoff']->format('d M Y').' and payment is due by '.$next['pay_by']->format('d M Y').'.');
        }

        return $mail
            ->action('View your investment and download your certificate', route('investor.investments.show', $i))
            ->line('Commodity trading involves risk. Returns are a share of actual trading profit and are not guaranteed.');
    }
}
