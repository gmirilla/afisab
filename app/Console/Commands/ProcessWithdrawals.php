<?php

namespace App\Console\Commands;

use App\Services\Treasury\WithdrawalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('afisab:process-withdrawals')]
#[Description('Mature investments whose early-withdrawal notice has run out and has nothing holding it back')]
class ProcessWithdrawals extends Command
{
    public function handle(WithdrawalService $withdrawals): int
    {
        $result = $withdrawals->processDue();
        $this->info("Matured {$result['matured']}, still waiting {$result['waiting']}.");

        return self::SUCCESS;
    }
}
