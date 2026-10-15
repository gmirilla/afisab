<?php

/*
| Proves that money cannot be moved twice when several people (or one impatient double-click) act on the same
| record at the same instant. It needs a REAL database server (MySQL or PostgreSQL), because SQLite ignores
| row locks. It WIPES the database it is pointed at, so it refuses to run unless the name looks disposable.
|
|   $env:DB_CONNECTION="mysql"; $env:DB_DATABASE="afisab_concurrency"; ...
|   $env:AFISAB_CONCURRENCY_WIPE="yes"
|   php scripts/concurrency-check.php
*/

use App\Models\Commodity;
use App\Models\Employee;
use App\Models\InvestmentPlan;
use App\Models\LedgerAccount;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Hr\PayrollService;
use App\Services\Ledger\LedgerService;
use App\Services\Payments\PaymentService;
use App\Services\Performance\DistributionService;
use App\Services\Performance\PerformanceService;
use App\Services\SubscriptionService;
use App\Services\Trading\CycleService;
use App\Services\Trading\PurchaseService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$driver = config('database.default');
$name = config("database.connections.{$driver}.database");
if ($driver === 'sqlite' || getenv('AFISAB_CONCURRENCY_WIPE') !== 'yes' || ! preg_match('/test|concurrency|scratch/i', (string) $name)) {
    fwrite(STDERR, "Refusing to run: needs a MySQL/PostgreSQL database whose name contains test/concurrency/scratch, and AFISAB_CONCURRENCY_WIPE=yes.\nCurrent: {$driver} / {$name}\n");
    exit(2);
}

echo "Database: {$driver} / {$name}\nWiping and seeding...\n";
Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);

// ---- build one scenario per action ----
$admin = User::factory()->create()->assignRole('super-admin');
$accounts = User::factory()->create()->assignRole('accounts');
$hr = User::factory()->create()->assignRole('hr');
$sales = User::factory()->create()->assignRole('sales');
$procurement = User::factory()->create()->assignRole('procurement');

$makeInvestor = function (int $amount, bool $pay) {
    $user = User::factory()->create()->assignRole('investor');
    $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);
    $inv = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '12-month')->first(), $amount);
    if ($pay) {
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($inv));
    }

    return $inv->fresh();
};

$funded = $makeInvestor(5_000_000_00, true);                 // gives Main Operations cash
$newPayment = app(PaymentService::class)->startTransfer($makeInvestor(1_000_000_00, false));

$cycle = app(CycleService::class)->open('Race', Commodity::first(), null, $sales);
app(CycleService::class)->allocate($cycle, $funded, 2_000_000_00, $sales);
$supplier = Supplier::create(['name' => 'S', 'type' => 'aggregator', 'is_active' => true]);
$purchase = app(PurchaseService::class)->propose($cycle, $supplier, 10_000, 5_000, $procurement); // ₦500,000
app(PurchaseService::class)->approve($purchase, $admin);

app(PerformanceService::class)->recordCycleResult('RACE-C', [$funded->id => 2_000_000_00], 1_000_000_00);
$run = app(DistributionService::class)->calculate(now(), notify: false);
app(DistributionService::class)->approve($run, $admin);
$line = $run->lines()->firstOrFail();

Employee::create(['name' => 'Chika', 'basic_salary' => 100_000_00, 'is_active' => true]);
$payroll = app(PayrollService::class)->prepare(now()->format('Y-m'), $hr);
app(PayrollService::class)->approve($payroll, $admin);

$ledger = app(LedgerService::class);
$before = [
    'inventory' => $ledger->balance(LedgerAccount::INVENTORY),
    'payable' => $ledger->balance(LedgerAccount::INVESTOR_PROFIT_PAYABLE),
    'payroll' => $ledger->balance(LedgerAccount::PAYROLL_EXPENSE),
    'funds' => $ledger->balance(LedgerAccount::INVESTOR_FUNDS),
];
$entriesBefore = DB::table('journal_entries')->count();

// ---- race ----
const WORKERS = 8;
$races = [
    ['confirm-payment', $newPayment->id, $accounts->id, 'allows repeats (idempotent)'],
    ['pay-purchase', $purchase->id, $accounts->id, 'exactly one pays'],
    ['pay-line', $line->id, $accounts->id, 'exactly one pays'],
    ['pay-payroll', $payroll->id, $accounts->id, 'exactly one pays'],
];

$failures = 0;
foreach ($races as [$action, $id, $userId, $expect]) {
    $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'afisab-barrier-'.uniqid();
    $procs = [];
    for ($i = 0; $i < WORKERS; $i++) {
        $cmd = [PHP_BINARY, __DIR__.'/concurrency-worker.php', $action, (string) $id, $barrier, (string) $userId];
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        $procs[] = [$p, $pipes];
    }
    usleep(1_500_000); // let every worker boot and reach the barrier
    touch($barrier);   // starting gun

    $out = [];
    foreach ($procs as [$p, $pipes]) {
        $out[] = trim(stream_get_contents($pipes[1]).stream_get_contents($pipes[2]));
        proc_close($p);
    }
    @unlink($barrier);

    $ok = count(array_filter($out, fn ($o) => $o === 'OK'));
    $errors = array_filter($out, fn ($o) => str_starts_with($o, 'ERROR'));
    $pass = $action === 'confirm-payment' ? $ok >= 1 && ! $errors : $ok === 1 && ! $errors;
    $failures += $pass ? 0 : 1;
    printf("%-16s %d workers: %d OK, %d refused, %d errors  -> %s (%s)\n", $action, WORKERS, $ok, count(array_filter($out, fn ($o) => str_starts_with($o, 'REFUSED'))), count($errors), $pass ? 'PASS' : 'FAIL', $expect);
    foreach (array_unique($errors) as $e) {
        echo "    $e\n";
    }
}

// ---- verify the money ----
$after = [
    'inventory' => $ledger->balance(LedgerAccount::INVENTORY),
    'payable' => $ledger->balance(LedgerAccount::INVESTOR_PROFIT_PAYABLE),
    'payroll' => $ledger->balance(LedgerAccount::PAYROLL_EXPENSE),
    'funds' => $ledger->balance(LedgerAccount::INVESTOR_FUNDS),
];
$expectDelta = [
    'inventory' => 50_000_000,                                  // purchase paid once
    'payable' => -$line->fresh()->investor_share,               // liability cleared once
    'payroll' => 10_000_000,                                    // gross salary expensed once
    'funds' => 1_000_000_00,                                    // new investor's money booked once
];
foreach ($expectDelta as $k => $delta) {
    $actual = $after[$k] - $before[$k];
    $pass = $actual === $delta;
    $failures += $pass ? 0 : 1;
    printf("%-10s change %15d (expected %15d) -> %s\n", $k, $actual, $delta, $pass ? 'PASS' : 'FAIL');
}

$debits = (int) DB::table('journal_lines')->where('direction', 'debit')->sum('amount');
$credits = (int) DB::table('journal_lines')->where('direction', 'credit')->sum('amount');
$balanced = $debits === $credits;
$failures += $balanced ? 0 : 1;
printf("ledger debits %d vs credits %d -> %s\n", $debits, $credits, $balanced ? 'PASS (balanced)' : 'FAIL');
printf("journal entries added by the race: %d\n", DB::table('journal_entries')->count() - $entriesBefore);

echo $failures === 0 ? "\nALL CHECKS PASSED: no double payments under concurrency.\n" : "\n{$failures} CHECK(S) FAILED.\n";
exit($failures === 0 ? 0 : 1);
