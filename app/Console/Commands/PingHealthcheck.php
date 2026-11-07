<?php

namespace App\Console\Commands;

use App\Support\HealthPinger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('afisab:health-ping')]
#[Description('Report the health checks to the Healthchecks.io-style ping URL (AFISAB_HEALTHCHECK_PING_URL); runs every 5 minutes')]
class PingHealthcheck extends Command
{
    public function handle(): int
    {
        $sent = HealthPinger::ping();
        $this->line(match ($sent) {
            'off' => 'No ping URL is set (AFISAB_HEALTHCHECK_PING_URL), nothing sent.',
            'ok' => 'Everything is fine: success ping sent.',
            'fail' => 'A check is failing: failure ping sent with the details.',
            default => 'The ping could not be delivered (see the log).',
        });

        return $sent === 'error' ? self::FAILURE : self::SUCCESS;
    }
}
