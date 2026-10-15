<?php

namespace App\Console\Commands;

use App\Services\MaturityService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('afisab:process-maturities')]
#[Description('Send maturity reminders and mark due investments as matured')]
class ProcessMaturities extends Command
{
    public function handle(MaturityService $maturities): int
    {
        $result = $maturities->process();
        $this->info("Reminded {$result['reminded']}, matured {$result['matured']}.");

        return self::SUCCESS;
    }
}
