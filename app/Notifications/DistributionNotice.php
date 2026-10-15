<?php

namespace App\Notifications;

use App\Models\DistributionRun;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Staff-facing: a monthly distribution run needs attention. */
class DistributionNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DistributionRun $run, public string $subject, public string $line) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

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
