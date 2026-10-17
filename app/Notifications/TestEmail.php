<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/** Sent by `php artisan afisab:test-mail` to prove the mail settings and the branded template work. */
class TestEmail extends AfisabNotification
{
    public $afterCommit = true;

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('AFISAB test email')
            ->greeting('Mail is working')
            ->line('If you can read this, this installation can send email, and the AFISAB template is in use.')
            ->line('Sent from '.config('app.url').' using the "'.config('mail.default').'" mailer at '.now()->format('d M Y H:i').'.')
            ->action('Open the site', config('app.url'));
    }
}
