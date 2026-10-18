<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Notifications\Messages\MailMessage;

/** To the investor: the notice has run, the policy has matured early and the payout is being prepared. */
class WithdrawalMatured extends AfisabNotification
{
    public $afterCommit = true;

    public function __construct(public WithdrawalRequest $request) {}

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->request->loadMissing('investment')->investment;

        return (new MailMessage)
            ->subject('Your investment has matured early')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your notice period has ended and investment {$i->reference} has matured early, as you asked.")
            ->line('Any profit still held back for your payment frequency is settled in the monthly profit run for this month. Your capital is now being prepared for payment to your bank account, which needs a second person to approve it. We will email you when it has been paid.')
            ->line('Please make sure your bank details are up to date.')
            ->action('View your investment', route('investor.investments.show', $i));
    }
}
