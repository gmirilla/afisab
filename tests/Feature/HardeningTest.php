<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Buyer;
use App\Models\CapitalPayout;
use App\Models\Commodity;
use App\Models\CycleExpense;
use App\Models\Employee;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\TakafulPolicy;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\Hr\PayrollService;
use App\Services\Ledger\LedgerService;
use App\Services\Payments\PaymentService;
use App\Services\Performance\DistributionService;
use App\Services\Performance\PerformanceService;
use App\Services\Risk\TakafulService;
use App\Services\SubscriptionService;
use App\Services\Trading\CycleService;
use App\Services\Trading\ExpenseService;
use App\Services\Trading\PurchaseService;
use App\Services\Trading\SaleService;
use App\Services\Treasury\CapitalPayoutService;
use App\Services\Treasury\TreasuryService;
use App\Support\SchedulerHealth;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    private User $admin;

    private User $accounts;

    private User $hr;

    private User $sales;

    private User $procurement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-12 10:00'));
        $this->ledger = app(LedgerService::class);

        $this->admin = User::factory()->create()->assignRole('super-admin');
        $this->accounts = User::factory()->create()->assignRole('accounts');
        $this->hr = User::factory()->create()->assignRole('hr');
        $this->sales = User::factory()->create()->assignRole('sales');
        $this->procurement = User::factory()->create()->assignRole('procurement');
    }

    private function investment(int $amount = 5_000_000_00, string $name = 'Ada Obi'): Investment
    {
        $user = User::factory()->create(['name' => $name])->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '12-month')->first(), $amount);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh();
    }

    // =====================================================================
    // Route authorization audit
    // =====================================================================

    public function test_the_only_routes_open_to_the_public_are_the_expected_ones(): void
    {
        $public = collect(Route::getRoutes())
            ->filter(fn ($r) => ! collect($r->gatherMiddleware())->contains(fn ($m) => is_string($m) && ($m === 'auth' || str_starts_with($m, 'auth:'))))
            ->map(fn ($r) => implode('|', array_diff($r->methods(), ['HEAD'])).' '.$r->uri())
            ->sort()->values()->all();

        $this->assertSame([
            'GET /', 'GET forgot-password', 'GET login', 'GET register', 'GET reset-password/{token}', 'GET two-factor-challenge',
            'GET up', 'GET verify', 'GET verify/{token}', 'POST forgot-password', 'POST login', 'POST register',
            'POST reset-password', 'POST two-factor-challenge', 'POST verify', 'POST webhooks/paystack',
        ], $public, 'A route became public (or a public one disappeared). Review it before launch.');
    }

    public function test_every_back_office_route_demands_a_role_or_permission(): void
    {
        foreach (Route::getRoutes() as $route) {
            $middleware = collect($route->gatherMiddleware())->filter(fn ($m) => is_string($m));
            $uri = $route->uri();

            if (str_starts_with($uri, 'admin')) {
                $this->assertTrue(
                    $middleware->contains(fn ($m) => str_starts_with($m, 'permission:') || str_starts_with($m, 'role:')),
                    "Route [{$uri}] has no permission/role middleware",
                );
            }
            if (str_starts_with($uri, 'investor')) {
                $this->assertContains('role:investor', $middleware->all(), "Route [{$uri}] is not restricted to investors");
            }
        }
    }

    public function test_anonymous_visitors_are_sent_to_login_from_every_signed_in_page(): void
    {
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods()) || str_contains($route->uri(), '{') || ! preg_match('#^(admin|investor|dashboard|account)#', $route->uri())) {
                continue;
            }
            $this->get('/'.$route->uri())->assertRedirect(route('login'));
            $count++;
        }
        $this->assertGreaterThan(20, $count);
    }

    public function test_investors_get_403_on_every_back_office_page(): void
    {
        $investor = $this->investment()->user;
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods()) || str_contains($route->uri(), '{') || ! str_starts_with($route->uri(), 'admin')) {
                continue;
            }
            $this->actingAs($investor)->get('/'.$route->uri())->assertForbidden();
            $count++;
        }
        $this->assertGreaterThan(20, $count);
    }

    public function test_staff_get_403_on_the_investor_portal(): void
    {
        foreach (['investor.dashboard', 'investor.plans', 'investor.profile'] as $name) {
            $this->actingAs($this->accounts)->get(route($name))->assertForbidden();
            $this->actingAs($this->admin)->get(route($name))->assertForbidden();
        }
    }

    public function test_an_investor_cannot_touch_another_investors_payment_or_proof(): void
    {
        Storage::fake();
        $victim = $this->investment(1_000_000_00, 'Victim');
        $attacker = $this->investment(1_000_000_00, 'Attacker');
        $pending = app(SubscriptionService::class)->subscribe($victim->user, InvestmentPlan::first(), 500_000_00);
        $payment = app(PaymentService::class)->startTransfer($pending);

        $this->actingAs($attacker->user)->post(route('investor.payments.proof', $payment), ['proof' => UploadedFile::fake()->image('x.png')])->assertNotFound();
        $this->actingAs($attacker->user)->post(route('investor.payments.transfer', $pending))->assertNotFound();
        $this->actingAs($attacker->user)->delete(route('investor.investments.cancel', $pending))->assertNotFound();
        $this->actingAs($attacker->user)->get(route('investor.payments.callback', ['reference' => $payment->reference]))->assertNotFound();
    }

    // =====================================================================
    // Browser-side protections
    // =====================================================================

    public function test_security_headers_and_content_security_policy_are_sent(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('https://checkout.paystack.com', $csp); // payment redirect must be allowed
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    }

    public function test_no_view_uses_inline_scripts_or_event_handlers(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $content = file_get_contents($file->getPathname());
            $this->assertDoesNotMatchRegularExpression('/\son(click|submit|change|load|error|input|focus)\s*=/i', $content, "{$file->getRelativePathname()} has an inline event handler");
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)/i', $content, "{$file->getRelativePathname()} has an inline <script>");
        }
    }

    public function test_the_private_disk_is_not_reachable_over_the_web(): void
    {
        Storage::disk('local')->put('kyc/secret-id.jpg', 'private');

        $this->get('/storage/kyc/secret-id.jpg')->assertNotFound();
        $this->assertFileExists(Storage::disk('local')->path('kyc/secret-id.jpg'));
        Storage::disk('local')->delete('kyc/secret-id.jpg');
    }

    public function test_error_pages_are_branded_and_leak_nothing(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('Page not found')->assertDontSee('Illuminate')->assertDontSee('vendor');

        $investor = $this->investment()->user;
        $this->actingAs($investor)->get(route('admin.funds'))->assertForbidden()->assertSee('Access denied');
    }

    public function test_file_uploads_are_restricted_by_type_and_size(): void
    {
        Storage::fake();
        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800']);
        $base = ['phone' => '08031234567', 'date_of_birth' => '1990-01-01', 'address' => '1 Street', 'bvn' => '12345678901', 'bank_name' => 'B', 'account_number' => '0123456789', 'account_name' => 'N'];

        $this->actingAs($user)->put(route('investor.profile.update'), $base + ['id_document' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php')])->assertSessionHasErrors('id_document');
        $this->actingAs($user)->put(route('investor.profile.update'), $base + ['id_document' => UploadedFile::fake()->create('big.pdf', 5_000, 'application/pdf')])->assertSessionHasErrors('id_document');
        $this->actingAs($user)->put(route('investor.profile.update'), $base + ['id_document' => UploadedFile::fake()->create('ok.pdf', 100, 'application/pdf')])->assertSessionHasNoErrors();
    }

    // =====================================================================
    // Sign-in protection and audit
    // =====================================================================

    public function test_sign_ins_and_failures_are_recorded_without_the_password(): void
    {
        $user = User::factory()->create(['password' => 'Str0ngPassword1']);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password-123']);
        $failed = Activity::where('log_name', 'auth')->where('description', 'Failed sign-in attempt')->first();
        $this->assertNotNull($failed);
        $this->assertSame($user->email, $failed->properties['email']);
        $this->assertStringNotContainsString('wrong-password-123', $failed->properties->toJson());

        $this->post('/login', ['email' => $user->email, 'password' => 'Str0ngPassword1']);
        $this->assertNotNull(Activity::where('log_name', 'auth')->where('description', 'Signed in')->where('causer_id', $user->id)->first());
    }

    public function test_repeated_wrong_passwords_are_throttled(): void
    {
        $user = User::factory()->create(['password' => 'Str0ngPassword1']);

        $statuses = collect(range(1, 7))->map(fn () => $this->post('/login', ['email' => $user->email, 'password' => 'nope-nope-nope'])->getStatusCode());

        $this->assertContains(429, $statuses->all(), 'Brute-force attempts were not throttled');
        $this->post('/login', ['email' => $user->email, 'password' => 'Str0ngPassword1']); // even the right password is refused while locked out
        $this->assertGuest();
    }

    // =====================================================================
    // Two-factor authentication for staff
    // =====================================================================

    public function test_staff_without_two_factor_are_held_at_the_security_page_when_enforced(): void
    {
        config(['afisab.require_2fa_for_staff' => true]);

        $this->actingAs($this->accounts)->get(route('admin.funds'))->assertRedirect(route('account.security'));
        $this->actingAs($this->accounts)->get(route('admin.dashboard'))->assertRedirect(route('account.security'));
        $this->actingAs($this->accounts)->get(route('account.security'))->assertOk()->assertSee('required for staff');

        $investor = $this->investment()->user;
        $this->actingAs($investor)->get(route('investor.dashboard'))->assertOk(); // investors are not forced
    }

    public function test_enforcement_can_be_off_for_development(): void
    {
        config(['afisab.require_2fa_for_staff' => false]);

        $this->actingAs($this->accounts)->get(route('admin.funds'))->assertOk();
    }

    public function test_staff_can_set_up_two_factor_and_are_then_let_in(): void
    {
        config(['afisab.require_2fa_for_staff' => true]);
        $staff = $this->accounts;

        $this->actingAs($staff)->post(route('account.security.enable'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($staff)->post(route('account.security.enable'), ['password' => 'password'])->assertRedirect(route('account.security'));
        $staff->refresh();
        $this->assertNotNull($staff->two_factor_secret);
        $this->assertNull($staff->two_factor_confirmed_at);

        $this->actingAs($staff)->get(route('account.security'))->assertOk()->assertSee('<svg', false);
        $this->actingAs($staff)->get(route('admin.funds'))->assertRedirect(route('account.security')); // still held until confirmed

        $this->actingAs($staff)->post(route('account.security.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');

        $code = (new Google2FA)->getCurrentOtp(decrypt($staff->two_factor_secret));
        $this->actingAs($staff)->post(route('account.security.confirm'), ['code' => $code])->assertSessionHasNoErrors();
        $this->assertNotNull($staff->fresh()->two_factor_confirmed_at);

        $this->actingAs($staff->fresh())->get(route('admin.funds'))->assertOk();
    }

    public function test_required_two_factor_cannot_be_turned_off_by_staff_but_investors_may(): void
    {
        config(['afisab.require_2fa_for_staff' => true]);
        $staff = $this->accounts;
        $staff->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_recovery_codes' => encrypt('[]'), 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($staff)->delete(route('account.security.disable'), ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertNotNull($staff->fresh()->two_factor_confirmed_at);

        $investor = $this->investment()->user;
        $investor->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_recovery_codes' => encrypt('[]'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($investor)->delete(route('account.security.disable'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertNull($investor->fresh()->two_factor_confirmed_at);
    }

    // =====================================================================
    // Double-submit / stale-record protection on every money movement
    // =====================================================================

    private function entries(): int
    {
        return JournalEntry::count();
    }

    public function test_confirming_a_payment_twice_from_stale_copies_books_it_once(): void
    {
        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::first(), 1_000_000_00);
        $payment = app(PaymentService::class)->startTransfer($investment);
        [$a, $b] = [Payment::find($payment->id), Payment::find($payment->id)]; // two browser tabs

        app(PaymentService::class)->confirm($a, $this->accounts);
        $after = $this->entries();
        app(PaymentService::class)->confirm($b, $this->accounts);

        $this->assertSame($after, $this->entries());
        $this->assertSame(1_000_000_00, $this->ledger->balance(LedgerAccount::INVESTOR_FUNDS));
    }

    public function test_paying_a_purchase_twice_from_stale_copies_pays_once(): void
    {
        $inv = $this->investment();
        $cycle = app(CycleService::class)->open('A', Commodity::first(), null, $this->sales);
        app(CycleService::class)->allocate($cycle, $inv, 1_000_000_00, $this->sales);
        $cycle = app(CycleService::class)->approve($cycle, $this->admin);
        $supplier = Supplier::create(['name' => 'S', 'type' => 'aggregator', 'is_active' => true]);
        $purchase = app(PurchaseService::class)->propose($cycle, $supplier, 1_000, 5_000, $this->procurement);
        app(PurchaseService::class)->approve($purchase, $this->admin);
        [$a, $b] = [Purchase::find($purchase->id), Purchase::find($purchase->id)];

        app(PurchaseService::class)->pay($a, 'NIP-1', $this->accounts);
        $after = $this->entries();

        $this->expectException(BusinessRuleException::class);
        try {
            app(PurchaseService::class)->pay($b, 'NIP-2', $this->accounts);
        } finally {
            $this->assertSame($after, $this->entries());
            $this->assertSame(5_000_000, $this->ledger->balance(LedgerAccount::INVENTORY));
        }
    }

    public function test_paying_an_expense_twice_from_stale_copies_pays_once(): void
    {
        $inv = $this->investment();
        $cycle = app(CycleService::class)->open('A', Commodity::first(), null, $this->sales);
        app(CycleService::class)->allocate($cycle, $inv, 1_000_000_00, $this->sales);
        $cycle = app(CycleService::class)->approve($cycle, $this->admin);
        $expense = app(ExpenseService::class)->propose($cycle, 'transport', 'Haulage', 10_000_00, $this->sales);
        [$a, $b] = [CycleExpense::find($expense->id), CycleExpense::find($expense->id)];

        app(ExpenseService::class)->pay($a, 'NIP', $this->accounts);
        $after = $this->entries();

        $this->expectException(BusinessRuleException::class);
        try {
            app(ExpenseService::class)->pay($b, 'NIP', $this->accounts);
        } finally {
            $this->assertSame($after, $this->entries());
        }
    }

    public function test_receiving_a_buyers_payment_twice_books_revenue_once(): void
    {
        $inv = $this->investment();
        $cycle = app(CycleService::class)->open('A', Commodity::first(), null, $this->sales);
        app(CycleService::class)->allocate($cycle, $inv, 1_000_000_00, $this->sales);
        $cycle = app(CycleService::class)->approve($cycle, $this->admin);
        $supplier = Supplier::create(['name' => 'S', 'type' => 'aggregator', 'is_active' => true]);
        $purchase = app(PurchaseService::class)->propose($cycle, $supplier, 1_000, 5_000, $this->procurement);
        app(PurchaseService::class)->approve($purchase, $this->admin);
        app(PurchaseService::class)->pay($purchase, 'P', $this->accounts);
        $lot = app(PurchaseService::class)->receive($purchase, ['quantity_kg' => 1_000, 'qa_passed' => true], $this->procurement);
        $buyer = Buyer::create(['name' => 'B', 'type' => 'local', 'is_active' => true]);
        $sale = app(SaleService::class)->create($cycle, $buyer, $lot, 1_000, 7_000, $this->sales);
        app(SaleService::class)->confirm($sale, $this->sales);
        [$a, $b] = [Sale::find($sale->id), Sale::find($sale->id)];

        app(SaleService::class)->receivePayment($a, 'B1', $this->accounts);
        $after = $this->entries();

        $this->expectException(BusinessRuleException::class);
        try {
            app(SaleService::class)->receivePayment($b, 'B1', $this->accounts);
        } finally {
            $this->assertSame($after, $this->entries());
            $this->assertSame(7_000_000, $this->ledger->balance(LedgerAccount::INVESTOR_PROFIT));
        }
    }

    public function test_approving_and_paying_a_distribution_twice_moves_money_once(): void
    {
        $inv = $this->investment();
        app(PerformanceService::class)->recordCycleResult('C1', [$inv->id => $inv->amount], 1_000_000_00);
        $service = app(DistributionService::class);
        $run = $service->calculate(Carbon::parse('2026-10-01'), notify: false);
        [$r1, $r2] = [$run->fresh(), $run->fresh()];

        $service->approve($r1, $this->admin);
        $liability = $this->ledger->balance(LedgerAccount::INVESTOR_PROFIT_PAYABLE);
        try {
            $service->approve($r2, User::factory()->create()->assignRole('super-admin'));
            $this->fail('Run approved twice');
        } catch (BusinessRuleException) {
            $this->assertSame($liability, $this->ledger->balance(LedgerAccount::INVESTOR_PROFIT_PAYABLE));
        }

        $line = $run->lines()->first();
        [$l1, $l2] = [$line->fresh(), $line->fresh()];
        $service->markLinePaid($l1, 'NIP-1', $this->accounts);
        $cash = $this->ledger->balance(LedgerAccount::MAIN_OPS);

        $this->expectException(BusinessRuleException::class);
        try {
            $service->markLinePaid($l2, 'NIP-2', $this->accounts);
        } finally {
            $this->assertSame($cash, $this->ledger->balance(LedgerAccount::MAIN_OPS));
        }
    }

    public function test_paying_a_capital_payout_twice_returns_principal_once(): void
    {
        $inv = $this->investment();
        $inv->update(['status' => 'matured']);
        $this->ledger->transfer(LedgerAccount::INVENTORY, LedgerAccount::MAIN_OPS, 300_000_00, 'proceeds'); // cover the 5% reserve gap
        $service = app(CapitalPayoutService::class);
        $payout = $service->prepare($inv->fresh(), $this->accounts);
        $service->approve($payout, $this->admin);
        [$a, $b] = [CapitalPayout::find($payout->id), CapitalPayout::find($payout->id)];

        $service->markPaid($a, 'NIP-1', $this->accounts);
        $cash = $this->ledger->balance(LedgerAccount::MAIN_OPS);

        $this->expectException(BusinessRuleException::class);
        try {
            $service->markPaid($b, 'NIP-2', $this->accounts);
        } finally {
            $this->assertSame($cash, $this->ledger->balance(LedgerAccount::MAIN_OPS));
        }
    }

    public function test_paying_payroll_or_a_premium_twice_moves_money_once(): void
    {
        $this->investment();
        Employee::create(['name' => 'Chika', 'basic_salary' => 100_000_00, 'is_active' => true]);
        $payroll = app(PayrollService::class);
        $run = $payroll->prepare('2026-10', $this->hr);
        $payroll->approve($run, $this->admin);
        [$a, $b] = [$run->fresh(), $run->fresh()];
        $payroll->pay($a, 'NIP', $this->accounts);
        $cash = $this->ledger->balance(LedgerAccount::MAIN_OPS);
        try {
            $payroll->pay($b, 'NIP', $this->accounts);
            $this->fail('Payroll paid twice');
        } catch (BusinessRuleException) {
            $this->assertSame($cash, $this->ledger->balance(LedgerAccount::MAIN_OPS));
        }

        $takaful = app(TakafulService::class);
        $policy = $takaful->create(['provider' => 'P', 'policy_number' => 'TK-1', 'description' => 'x', 'premium' => 10_000_00, 'cover_amount' => 1_000_000_00, 'start_date' => '2026-10-01', 'end_date' => '2027-09-30'], $this->accounts);
        [$p1, $p2] = [TakafulPolicy::find($policy->id), TakafulPolicy::find($policy->id)];
        $takaful->payPremium($p1, 'NIP', $this->accounts);
        $risk = $this->ledger->balance(LedgerAccount::RISK_FUND);

        $this->expectException(BusinessRuleException::class);
        try {
            $takaful->payPremium($p2, 'NIP', $this->accounts);
        } finally {
            $this->assertSame($risk, $this->ledger->balance(LedgerAccount::RISK_FUND));
        }
    }

    public function test_the_ledger_refuses_a_duplicate_reference_and_saves_nothing(): void
    {
        $this->ledger->transfer(LedgerAccount::INVENTORY, LedgerAccount::MAIN_OPS, 100, 'first', null, 'REF-DUP');
        $before = $this->entries();
        $lines = DB::table('journal_lines')->count();

        try {
            $this->ledger->transfer(LedgerAccount::INVENTORY, LedgerAccount::MAIN_OPS, 100, 'second', null, 'REF-DUP');
            $this->fail('Duplicate reference accepted');
        } catch (\Throwable) {
            $this->assertSame($before, $this->entries());
            $this->assertSame($lines, DB::table('journal_lines')->count());
        }
    }

    // =====================================================================
    // Efficiency
    // =====================================================================

    public function test_the_per_policy_funds_view_does_not_query_once_per_policy(): void
    {
        $this->investment(1_000_000_00, 'One');
        $this->investment(1_000_000_00, 'Two');
        DB::enableQueryLog();
        app(TreasuryService::class)->policyRows();
        $small = count(DB::getQueryLog());

        foreach (range(1, 20) as $i) {
            $this->investment(1_000_000_00, "Investor {$i}");
        }
        DB::flushQueryLog();
        $rows = app(TreasuryService::class)->policyRows();
        $large = count(DB::getQueryLog());

        $this->assertCount(22, $rows);
        $this->assertLessThanOrEqual($small + 2, $large, "Query count grew from {$small} to {$large} with more policies (N+1)");
    }

    // =====================================================================
    // Launch readiness
    // =====================================================================

    public function test_preflight_flags_problems_and_exits_non_zero(): void
    {
        // The test environment uses an array cache and SQLite, both unfit for production.
        $this->artisan('afisab:preflight')
            ->expectsOutputToContain('APP_KEY is set')
            ->expectsOutputToContain('Cache is persistent')
            ->expectsOutputToContain('Roles are seeded')
            ->assertFailed();
    }

    public function test_blank_or_placeholder_bank_details_do_not_pass_preflight(): void
    {
        foreach ([
            ['name' => '', 'account_number' => '', 'account_name' => ''],                           // blank .env values
            ['name' => 'Set AFISAB_BANK_NAME', 'account_number' => '0000000000', 'account_name' => 'AFISAB NIG. LTD'], // defaults
            ['name' => 'Test Bank', 'account_number' => '12345', 'account_name' => 'AFISAB NIG. LTD'],             // not 10 digits
        ] as $bank) {
            config(['afisab.bank' => $bank]);
            Artisan::call('afisab:preflight');
            $this->assertMatchesRegularExpression('/(warn|FAIL)\s*\|\s*Bank transfer details are real/', Artisan::output(), json_encode($bank));
        }

        config(['afisab.bank' => ['name' => 'Test Bank', 'account_number' => '0123456789', 'account_name' => 'AFISAB NIG. LTD']]);
        Artisan::call('afisab:preflight');
        $this->assertMatchesRegularExpression('/ok\s*\|\s*Bank transfer details are real/', Artisan::output());
    }

    public function test_the_web_root_can_be_a_separate_folder_for_the_cpanel_layout(): void
    {
        $original = public_path();

        config(['afisab.public_path' => '/home/example/public_html']);
        (new AppServiceProvider(app()))->register();
        $normalise = fn (string $p) => str_replace('\\', '/', $p); // Windows uses backslashes
        $this->assertSame('/home/example/public_html', $normalise(public_path()));
        $this->assertSame('/home/example/public_html/build/manifest.json', $normalise(public_path('build/manifest.json'))); // where @vite looks

        app()->usePublicPath($original);
    }

    public function test_the_heartbeat_task_is_scheduled_and_the_dashboard_warns_when_it_goes_quiet(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('heartbeat')->assertSuccessful();

        SchedulerHealth::beat();
        $this->assertSame('ok', SchedulerHealth::status());
        $this->actingAs($this->accounts)->get(route('admin.dashboard'))->assertOk()->assertDontSee('cron job');

        $this->travelTo(now()->addMinutes(30));
        $this->assertSame('stale', SchedulerHealth::status());
        $this->actingAs($this->accounts)->get(route('admin.dashboard'))->assertOk()->assertSee('stopped running');
        $this->actingAs($this->hr)->get(route('admin.dashboard'))->assertOk()->assertDontSee('stopped running'); // only people who rely on it

        Cache::forget(SchedulerHealth::KEY);
        $this->assertSame('unknown', SchedulerHealth::status());
    }
}
