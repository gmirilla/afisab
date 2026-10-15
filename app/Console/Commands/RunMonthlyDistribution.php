<?php

namespace App\Console\Commands;

use App\Exceptions\BusinessRuleException;
use App\Services\Performance\DistributionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('afisab:run-distribution {month? : YYYY-MM, defaults to the current month}')]
#[Description('Calculate the monthly investor profit distribution (scheduled for the cut-off day, the 27th)')]
class RunMonthlyDistribution extends Command
{
    public function handle(DistributionService $distributions): int
    {
        try {
            $month = $this->argument('month') ? Carbon::createFromFormat('Y-m', $this->argument('month')) : now();
            $run = $distributions->calculate($month);
        } catch (BusinessRuleException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Run {$run->period}: {$run->lines()->count()} policies, status {$run->status}.");

        return self::SUCCESS;
    }
}
