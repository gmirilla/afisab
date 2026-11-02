<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * "We received your request", sent to the person who used a website form. Deliberately fixed wording: nothing the
 * visitor typed appears in it (not even their name), so the public form cannot be abused to email strangers custom text.
 */
class EnquiryAcknowledged extends AfisabNotification
{
    public $afterCommit = true;

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We received your request')
            ->greeting('Hello,')
            ->line('Thank you for contacting '.config('afisab.company_name').'. We have received your request and a member of our team will respond using the details you gave us.')
            ->line('If you did not make this request, you can ignore this message and nothing further will happen.')
            ->line('Phone: '.config('afisab.contact.phone').' · Email: '.config('afisab.contact.email'));
    }
}
