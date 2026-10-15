<?php

namespace App\Console\Commands;

use App\Services\Performance\DistributionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('afisab:distribution-reminders')]
#[Description('Catch a missed distribution calculation and send approval / payment deadline reminders')]
class DistributionReminders extends Command
{
    public function handle(DistributionService $distributions): int
    {
        $done = $distributions->reminders();
        $this->info($done ? implode(', ', $done) : 'Nothing to do.');

        return self::SUCCESS;
    }
}
