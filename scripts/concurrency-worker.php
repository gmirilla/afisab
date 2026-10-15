<?php

/*
| One racing worker for scripts/concurrency-check.php. Waits at a barrier file, then performs the action.
| Prints "OK" when it did the work, or "REFUSED: <reason>" / "ERROR: <reason>" otherwise.
*/

use App\Exceptions\BusinessRuleException;
use App\Models\DistributionLine;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Hr\PayrollService;
use App\Services\Payments\PaymentService;
use App\Services\Performance\DistributionService;
use App\Services\Trading\PurchaseService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $action, $id, $barrier, $userId] = $argv;

$user = User::find((int) $userId);
$models = [
    'confirm-payment' => fn () => app(PaymentService::class)->confirm(Payment::find((int) $id), $user),
    'pay-purchase' => fn () => app(PurchaseService::class)->pay(Purchase::find((int) $id), 'RACE', $user),
    'pay-line' => fn () => app(DistributionService::class)->markLinePaid(DistributionLine::find((int) $id), 'RACE', $user),
    'pay-payroll' => fn () => app(PayrollService::class)->pay(PayrollRun::find((int) $id), 'RACE', $user),
];

// Load the (soon stale) model first, like a browser tab that was opened earlier, then wait for the starting gun.
while (! file_exists($barrier)) {
    usleep(500);
}

try {
    $models[$action]();
    echo 'OK';
} catch (BusinessRuleException $e) {
    echo 'REFUSED: '.$e->getMessage();
} catch (Throwable $e) {
    echo 'ERROR: '.get_class($e).': '.substr($e->getMessage(), 0, 120);
}
