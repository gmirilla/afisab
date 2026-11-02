<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('afisab:expire-unpaid')]
#[Description('Lapse unpaid subscription requests older than the configured number of days')]
class ExpireUnpaidSubscriptions extends Command
{
    public function handle(SubscriptionService $subscriptions): int
    {
        $this->info('Expired '.$subscriptions->expireUnpaid().' unpaid subscription request(s).');

        return self::SUCCESS;
    }
}
