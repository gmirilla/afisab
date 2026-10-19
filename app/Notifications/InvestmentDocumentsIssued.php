<?php

namespace App\Notifications;

use App\Models\PolicyDocument;
use App\Models\Receipt;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/** Sent once, when a payment is confirmed: the investment receipt and the investment agreement together. */
class InvestmentDocumentsIssued extends AfisabNotification
{
    public $afterCommit = true;

    public function __construct(public Receipt $receipt, public PolicyDocument $document) {}

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->document->loadMissing('investment')->investment;
        $mail = (new MailMessage)->greeting("Hello {$notifiable->name},")
            ->subject('Your AFISAB investment receipt and agreement')
            ->line("Thank you for investing. We have received your payment for investment {$i->reference}. Your investment receipt and your Mudharabah Investment Agreement are attached.")
            ->line("Receipt number: {$this->receipt->receipt_number}.")
            ->line("Policy number: {$this->document->policy_number}.");

        foreach ([[$this->receipt->file_path, $this->receipt->filename()], [$this->document->file_path, $this->document->filename()]] as [$path, $name]) {
            if (Storage::exists($path)) {
                $mail->attachData(Storage::get($path), $name, ['mime' => 'application/pdf']);
            }
        }

        return $mail->line('Please keep these documents safe. You can download them again at any time from your investment page.')
            ->action('View your investment', route('investor.investments.show', $i));
    }
}
