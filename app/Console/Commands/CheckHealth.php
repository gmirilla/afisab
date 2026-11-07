<?php

namespace App\Console\Commands;

use App\Support\HealthCheck;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('afisab:health')]
#[Description('Check the database, scheduler, queue and mail, as the /health monitoring URL does (exit code 1 if anything is wrong)')]
class CheckHealth extends Command
{
    public function handle(): int
    {
        $result = HealthCheck::run();
        foreach ($result['checks'] as $name => $check) {
            $this->line(sprintf('%s %-10s %s', $check['ok'] ? '<info>OK  </info>' : '<error>FAIL</error>', $name, $check['detail']));
        }

        return $result['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
