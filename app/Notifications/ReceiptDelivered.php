<?php

namespace App\Notifications;

use App\Models\Receipt;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/** A copy of the investment receipt, attached. Sent on request by the investor or staff. */
class ReceiptDelivered extends AfisabNotification
{
    public $afterCommit = true;

    public function __construct(public Receipt $receipt) {}

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->receipt->loadMissing('investment');
        $mail = (new MailMessage)->greeting("Hello {$notifiable->name},")
            ->subject('A copy of your AFISAB investment receipt')
            ->line("As requested, a copy of your investment receipt {$r->receipt_number} for investment {$r->investment->reference} is attached.");

        if (Storage::exists($r->file_path)) {
            $mail->attachData(Storage::get($r->file_path), $r->filename(), ['mime' => 'application/pdf']);
        }

        return $mail->action('View your investment', route('investor.investments.show', $r->investment));
    }
}
