<?php

namespace App\Notifications;

use App\Models\Enquiry;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** Sent to the shared enquiries mailbox when someone uses a form on the public website. */
class EnquiryReceived extends AfisabNotification
{
    public $afterCommit = true;

    public function __construct(public Enquiry $enquiry) {}

    public function toMail(object $notifiable): MailMessage
    {
        $e = $this->enquiry;
        $e->loadMissing('items');
        $summary = $e->commoditySummary(3);
        $subject = $e->label().($summary !== '' ? ": {$summary}" : '').' from '.($e->company ?: $e->name);

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('New website enquiry')
            ->line("Type: {$e->label()}")
            ->line("From: {$e->name}".($e->company ? " ({$e->company})" : ''))
            ->line('Phone: '.($e->phone ?: 'not given').' · Email: '.($e->email ?: 'not given'));

        foreach ($e->items as $item) {
            $mail->line('Commodity: '.$item->commodity.($item->quantity ? " ({$item->quantity})" : ''));
        }
        if ($e->items->isEmpty()) {
            foreach (['commodity' => 'Commodity', 'quantity' => 'Quantity'] as $field => $label) {
                if ($e->{$field}) {
                    $mail->line("{$label}: {$e->{$field}}");
                }
            }
        }
        if ($e->location) {
            $mail->line("Location: {$e->location}");
        }
        if ($e->message) {
            $mail->line('Message: '.Str::limit(strip_tags($e->message), 1500));
        }

        return $mail
            ->action('Open in the back office', route('admin.enquiries.show', $e))
            ->line('Reply to the sender directly using the details above. Mark the enquiry as contacted in the back office so nobody repeats the call.');
    }
}
