<?php

namespace App\Console\Commands;

use App\Models\LedgerAccount;
use App\Models\RiskLimit;
use App\Models\User;
use App\Support\SchedulerHealth;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Throwable;

#[Signature('afisab:preflight {--strict : Treat warnings as failures}')]
#[Description('Check that this installation is configured safely for real investor money')]
class Preflight extends Command
{
    /** @var array<int, array{string, string, string}> */
    private array $results = [];

    public function handle(): int
    {
        $production = app()->environment('production');

        $this->check('APP_ENV is production', $production, $production ? 'production' : 'currently "'.app()->environment().'"', 'warn');
        $this->check('APP_DEBUG is off', ! config('app.debug'), config('app.debug') ? 'Debug mode shows stack traces and secrets to visitors' : 'off', $production ? 'fail' : 'warn');
        $this->check('APP_KEY is set', (bool) config('app.key'), 'Needed to encrypt BVNs and sessions. Back it up.', 'fail');
        $this->check('APP_URL uses HTTPS', str_starts_with((string) config('app.url'), 'https://'), (string) config('app.url'), $production ? 'fail' : 'warn');
        $this->check('Time zone is Africa/Lagos', config('app.timezone') === 'Africa/Lagos', 'currently '.config('app.timezone').'; "the 27th" is judged in this zone', 'warn');

        $driver = config('database.default');
        $this->check('Database is not SQLite', $driver !== 'sqlite', "driver: {$driver}", $production ? 'fail' : 'warn');
        $this->check('Cache is persistent', ! in_array(config('cache.default'), ['array', 'null'], true), 'driver: '.config('cache.default'), 'fail');
        $this->check('Mail is configured to send', ! in_array(config('mail.default'), ['log', 'array'], true), 'mailer: '.config('mail.default').' (verification, reset and payment emails)', $production ? 'fail' : 'warn');
        $this->check('Session cookie is HTTPS-only', (bool) config('session.secure'), 'SESSION_SECURE_COOKIE', $production ? 'fail' : 'warn');
        $this->check('Sessions are encrypted', (bool) config('session.encrypt'), 'SESSION_ENCRYPT', 'warn');

        $secret = app(\App\Services\Payments\PaystackSettings::class)->secret();
        $this->check('Paystack secret key is set', $secret !== '', 'Admin > Paystack (or PAYSTACK_SECRET_KEY in .env)', 'warn');
        $this->check('Paystack key is a LIVE key', $secret === '' || str_starts_with($secret, 'sk_live_'), $secret === '' ? 'no key' : 'starts with '.substr($secret, 0, 8), $production ? 'fail' : 'warn');
        $banks = app(\App\Services\Payments\BankAccounts::class)->active();
        $bankReal = $banks->isNotEmpty() && $banks->every(fn ($b) => \App\Services\Payments\BankAccounts::isReal($b));
        $this->check('Bank transfer details are real', $bankReal, $bankReal ? $banks->map(fn ($b) => "{$b['name']} {$b['account_number']}")->implode(', ') : 'add real accounts under Treasury > Bank accounts (or AFISAB_BANK_* in .env): bank name, 10-digit number, account name', $production ? 'fail' : 'warn');

        $this->check('storage/ is writable', is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')), 'storage and bootstrap/cache', 'fail');
        $this->check('Front-end assets are built', file_exists(public_path('build/manifest.json')), 'public/build/manifest.json (run npm run build)', 'fail');

        try {
            $migrator = app('migrator');
            $files = array_keys($migrator->getMigrationFiles([database_path('migrations')]));
            $pending = array_diff($files, Schema::hasTable('migrations') ? $migrator->getRepository()->getRan() : []);
            $this->check('Database is migrated', count($pending) === 0, count($pending) ? count($pending).' pending migration(s): run php artisan migrate --force' : 'up to date', 'fail');

            $this->check('Roles are seeded', Role::where('name', 'super-admin')->exists() && Role::where('name', 'investor')->exists(), 'run php artisan db:seed --force', 'fail');
            $this->check('Ledger accounts are seeded', LedgerAccount::where('code', LedgerAccount::MAIN_OPS)->exists(), 'run php artisan db:seed --force', 'fail');

            $admins = User::role('super-admin')->whereNull('disabled_at')->get();
            $this->check('At least one active super-admin', $admins->isNotEmpty(), 'afisab:make-staff creates one', 'fail');

            $staff = User::whereHas('roles', fn ($q) => $q->where('name', '!=', 'investor'))->whereNull('disabled_at')->get();
            $without = $staff->filter(fn (User $u) => $u->two_factor_confirmed_at === null)->count();
            $this->check('Staff have two-factor authentication', $without === 0, $without.' of '.$staff->count().' active staff still without it', config('afisab.require_2fa_for_staff') ? 'fail' : 'warn');
            $this->check('Staff two-factor is enforced', (bool) config('afisab.require_2fa_for_staff'), 'AFISAB_REQUIRE_2FA', $production ? 'fail' : 'warn');

            $active = RiskLimit::where('is_active', true)->count();
            $this->check('Risk limits are switched on', $active > 0, $active.' of '.RiskLimit::count().' limits enforced (a Director decides; see Risk page)', 'warn');
        } catch (Throwable $e) {
            $this->check('Database is reachable', false, $e->getMessage(), 'fail');
        }

        $status = SchedulerHealth::status();
        $this->check('Scheduler cron is running', $status === 'ok', $status === 'unknown' ? 'no heartbeat yet (needs the every-minute cron)' : $status, 'warn');

        $this->table(['', 'Check', 'Detail'], array_map(fn ($r) => [$r[0], $r[1], $r[2]], $this->results));

        $fails = count(array_filter($this->results, fn ($r) => $r[0] === 'FAIL'));
        $warns = count(array_filter($this->results, fn ($r) => $r[0] === 'warn'));
        $this->line("{$fails} failure(s), {$warns} warning(s).");

        return $fails > 0 || ($this->option('strict') && $warns > 0) ? self::FAILURE : self::SUCCESS;
    }

    private function check(string $label, bool $ok, string $detail, string $severityIfNot): void
    {
        $this->results[] = [$ok ? 'ok' : ($severityIfNot === 'fail' ? 'FAIL' : 'warn'), $label, $detail];
    }
}
