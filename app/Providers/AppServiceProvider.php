<?php

namespace App\Providers;

use App\Models\BankAccount;
use App\Models\PaystackSetting;
use App\Models\PensionAdministrator;
use App\Models\StatutoryRemittance;
use App\Models\StatutoryRuleSet;
use App\Models\Buyer;
use App\Models\CapitalPayout;
use App\Models\CycleExpense;
use App\Models\JournalEntry;
use App\Models\DistributionLine;
use App\Models\DistributionRun;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\PolicyDocument;
use App\Models\Signatory;
use App\Models\WithdrawalRequest;
use App\Models\InventoryLot;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\InvestorProfile;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\RiskLimit;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\TakafulPolicy;
use App\Models\TradeCycle;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // cPanel split layout: tell Laravel where the web root really is (see deploy/index.php.cpanel).
        if ($path = config('afisab.public_path')) {
            $this->app->usePublicPath($path);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Lets the super-admin's queue page tell whether a worker is alive.
        \Illuminate\Support\Facades\Event::listen([\Illuminate\Queue\Events\JobProcessed::class, \Illuminate\Queue\Events\JobFailed::class, \Illuminate\Queue\Events\JobProcessing::class], fn () => \App\Support\QueueMonitor::touch());

        // Stable names for polymorphic ledger subjects (survives class renames).
        Relation::enforceMorphMap([
            'user' => User::class,
            'investment' => Investment::class,
            'investment_plan' => InvestmentPlan::class,
            'investor_profile' => InvestorProfile::class,
            'payment' => Payment::class,
            'distribution_run' => DistributionRun::class,
            'ledger_account' => LedgerAccount::class,
            'capital_payout' => CapitalPayout::class,
            'trade_cycle' => TradeCycle::class,
            'purchase' => Purchase::class,
            'sale' => Sale::class,
            'cycle_expense' => CycleExpense::class,
            'inventory_lot' => InventoryLot::class,
            'supplier' => Supplier::class,
            'buyer' => Buyer::class,
            'risk_limit' => RiskLimit::class,
            'takaful_policy' => TakafulPolicy::class,
            'employee' => Employee::class,
            'payroll_run' => PayrollRun::class,
            'distribution_line' => DistributionLine::class,
            'journal_entry' => JournalEntry::class,
            'bank_account' => BankAccount::class,
            'paystack_setting' => PaystackSetting::class,
            'enquiry' => Enquiry::class,
            'signatory' => Signatory::class,
            'withdrawal_request' => WithdrawalRequest::class,
            'policy_document' => PolicyDocument::class,
            'receipt' => \App\Models\Receipt::class,
            'company_stamp' => \App\Models\CompanyStamp::class,
            'company_detail' => \App\Models\CompanyDetail::class,
            'pension_administrator' => PensionAdministrator::class,
            'statutory_rule_set' => StatutoryRuleSet::class,
            'statutory_remittance' => StatutoryRemittance::class,
        ]);

        Password::defaults(fn () => app()->isProduction()
            ? Password::min(10)->mixedCase()->numbers()->uncompromised()
            : Password::min(10)->mixedCase()->numbers());

        // Sign-ins, failed attempts and sign-outs go into the audit trail (never the password).
        Event::listen(Login::class, fn (Login $e) => activity('auth')->causedBy($e->user)
            ->withProperties($this->requestContext())->log('Signed in'));
        Event::listen(Failed::class, fn (Failed $e) => activity('auth')
            ->withProperties($this->requestContext() + ['email' => $e->credentials['email'] ?? null])->log('Failed sign-in attempt'));
        Event::listen(Logout::class, fn (Logout $e) => $e->user ? activity('auth')->causedBy($e->user)
            ->withProperties($this->requestContext())->log('Signed out') : null);

        // Super-admin passes every permission check.
        Gate::before(fn (?User $user) => $user?->hasRole('super-admin') ? true : null);
    }

    /** @return array{ip: ?string, agent: ?string} */
    private function requestContext(): array
    {
        return ['ip' => request()->ip(), 'agent' => Str::limit((string) request()->userAgent(), 120, '')];
    }
}
