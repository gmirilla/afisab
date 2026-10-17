<?php

namespace App\Console\Commands;

use App\Notifications\TestEmail;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

#[Signature('afisab:test-mail {to : The address to send the test email to}')]
#[Description('Send a test email now (not queued) to check the mail settings')]
class TestMail extends Command
{
    public function handle(): int
    {
        $to = (string) $this->argument('to');
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error("\"{$to}\" is not a valid email address.");

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $this->line("Mailer: {$mailer}".(in_array($mailer, ['log', 'array'], true) ? ' (this does NOT deliver real email)' : ''));
        $this->line('From: '.config('mail.from.name').' <'.config('mail.from.address').'>');
        if ($mailer === 'smtp') {
            $this->line('SMTP: '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
        }

        try {
            Notification::route('mail', $to)->notifyNow(new TestEmail);
        } catch (Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent to {$to}. Check the inbox (and spam) to confirm it arrived.");

        return self::SUCCESS;
    }
}
