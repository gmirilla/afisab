<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/** To the investor: their notice of early withdrawal was received, with the dates. */
class WithdrawalRequestReceived extends AfisabNotification
{
    public $afterCommit = true;

    public function __construct(public WithdrawalRequest $request) {}

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request->loadMissing('investment');
        $i = $r->investment;

        return (new MailMessage)
            ->subject('We have received your notice of early withdrawal')
            ->greeting("Hello {$notifiable->name},")
            ->line("We received your notice to withdraw your capital of ".Money::format($i->amount)." from investment {$i->reference} before its term ends.")
            ->line("Your notice period runs until {$r->notice_ends_on->format('d F Y')}. Your investment stays active and keeps earning until then, and your capital will not be placed in new trading cycles.")
            ->line('As your agreement says, early withdrawal is subject to the availability of funds and the settlement of ongoing transactions. If any of your capital is still in a trading cycle when the notice ends, your withdrawal waits until that cycle closes. We will email you when your investment has matured and your payout is being prepared.')
            ->line('You can cancel this notice at any time before then from your investment page.')
            ->action('View your investment', route('investor.investments.show', $i));
    }
}
