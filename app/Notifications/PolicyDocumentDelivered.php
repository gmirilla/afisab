<?php

namespace App\Notifications;

use App\Models\PolicyDocument;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/** The investor's agreement, attached. Sent when it is first issued and whenever they or staff ask for a copy. */
class PolicyDocumentDelivered extends AfisabNotification
{
    public $afterCommit = true;

    /** @param  string  $reason  'issued' (first issue), 'reissued' (corrected version) or 'copy' (a requested copy) */
    public function __construct(public PolicyDocument $document, public string $reason = 'copy') {}

    public function toMail(object $notifiable): MailMessage
    {
        $doc = $this->document->loadMissing('investment');
        $i = $doc->investment;

        $mail = (new MailMessage)->greeting("Hello {$notifiable->name},");

        match ($this->reason) {
            'issued' => $mail->subject('Your AFISAB investment agreement')->line("Thank you for investing. Your Mudharabah Investment Agreement for investment {$i->reference} is attached."),
            'reissued' => $mail->subject('Your AFISAB investment agreement has been re-issued')->line("A corrected version of the agreement for investment {$i->reference} is attached. It replaces the earlier version.")->line('Reason: '.($doc->reason ?: 'correction')),
            default => $mail->subject('A copy of your AFISAB investment agreement')->line("As requested, a copy of your Mudharabah Investment Agreement for investment {$i->reference} is attached."),
        };

        if (Storage::exists($doc->file_path)) {
            $mail->attachData(Storage::get($doc->file_path), $doc->filename(), ['mime' => 'application/pdf']);
        }

        return $mail
            ->line("Policy number: {$doc->policy_number} (version {$doc->version}).")
            ->line('Please keep this document safe. You can download it again at any time from your investment page.')
            ->action('View your investment', route('investor.investments.show', $i));
    }
}
