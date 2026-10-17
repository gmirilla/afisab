<?php

namespace App\Notifications;

use App\Models\DistributionRun;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/** Staff-facing: a monthly distribution run needs attention. */
class DistributionNotice extends AfisabNotification
{
    public $afterCommit = true;

    public function __construct(public DistributionRun $run, public string $subject, public string $line) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($this->line)
            ->line('Investor share total: '.Money::format($this->run->total_investor).' across '.$this->run->lines()->where('investor_share', '>', 0)->count().' investor(s).')
            ->action('Open the distribution run', route('admin.distributions.show', $this->run));
    }
}
