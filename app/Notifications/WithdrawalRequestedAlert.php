<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/** Staff-facing: an investor has given notice of early withdrawal. */
class WithdrawalRequestedAlert extends AfisabNotification
{
    public $afterCommit = true;

    public function __construct(public WithdrawalRequest $request) {}

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request->loadMissing('investment.user');
        $i = $r->investment;

        return (new MailMessage)
            ->subject('Early withdrawal notice from '.$i->user->name)
            ->greeting("Hello {$notifiable->name},")
            ->line("{$i->user->name} has given notice to withdraw ".Money::format($i->amount)." from investment {$i->reference} early.")
            ->line("The notice period ends on {$r->notice_ends_on->format('d F Y')}. The policy matures early then, provided none of its capital is still in a trading cycle.".($r->reason ? " Reason given: {$r->reason}" : ''))
            ->line('Plan for the payout: the capital must be in Main Operations when the payout is paid.')
            ->action('Open early withdrawals', route('admin.withdrawals'));
    }
}
