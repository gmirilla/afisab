<?php

namespace Tests\Feature;

use App\Enums\ProfitFrequency;
use App\Models\CapitalPayout;
use App\Models\Commodity;
use App\Models\DistributionLine;
use App\Models\DistributionRun;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\TradeCycle;
use App\Models\User;
use App\Notifications\AfisabNotification;
use App\Notifications\CapitalPaid;
use App\Notifications\CapitalPayoutReady;
use App\Notifications\CycleAwaitingApproval;
use App\Notifications\DistributionNotice;
use App\Notifications\MaturityApproaching;
use App\Notifications\PaymentConfirmed;
use App\Notifications\ProfitPaid;
use App\Notifications\TestEmail;
use App\Services\Payments\PaymentService;
use App\Services\Performance\DistributionService;
use App\Services\SubscriptionService;
use App\Services\Trading\CycleService;
use App\Services\Treasury\CapitalPayoutService;
use App\Support\QueueHealth;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private User $accounts;

    private User $investor;

    private Investment $quarterly;

    private Investment $monthly;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
        $this->director = User::factory()->create(['name' => 'Dee Director'])->assignRole('super-admin');
        $this->accounts = User::factory()->create(['name' => 'Acc Ounts'])->assignRole('accounts');
        $this->quarterly = $this->policy('Qua Investor', ProfitFrequency::Quarterly);
        $this->monthly = $this->policy('Mon Investor', ProfitFrequency::Monthly);
        $this->investor = $this->quarterly->user;
    }

    private function policy(string $name, ProfitFrequency $frequency): Investment
    {
        $user = User::factory()->create(['name' => $name])->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved', 'bank_name' => 'Test Bank', 'account_number' => '0123456789', 'account_name' => $name]);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '12-month')->first(), 1_000_000_00, $frequency);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh();
    }

    private function line(Investment $i, ?string $from = '2026-07-21', ?string $to = '2026-10-21'): DistributionLine
    {
        $run = DistributionRun::firstOrCreate(['period' => '2026-10'], ['cutoff_date' => '2026-10-27', 'pay_by' => '2026-10-31', 'status' => DistributionRun::PAID, 'total_investor' => 250_000_00]);

        return DistributionLine::create([
            'distribution_run_id' => $run->id, 'investment_id' => $i->id, 'net_profit' => 1_000_000_00, 'retained' => 0, 'distributable' => 1_000_000_00,
            'investor_share' => 250_000_00, 'company_share' => 0, 'status' => DistributionLine::PAID, 'paid_at' => now(), 'payment_reference' => 'NIP-777',
            'frequency' => $i->profit_frequency->value, 'covers_from' => $from, 'covers_to' => $to,
        ]);
    }

    private function sentMail(): array
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->map(fn ($m) => $m->getOriginalMessage())->all();
    }

    // ---- the pattern every notification follows ----

    public function test_every_notification_is_queued_after_commit_and_retried(): void
    {
        $classes = collect(glob(app_path('Notifications/*.php')))->map(fn ($f) => 'App\\Notifications\\'.basename($f, '.php'))->reject(fn ($c) => $c === AfisabNotification::class);
        $this->assertGreaterThanOrEqual(8, $classes->count());

        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            $this->assertTrue($reflection->isSubclassOf(AfisabNotification::class), "$class should extend AfisabNotification");
            $this->assertTrue($reflection->implementsInterface(ShouldQueue::class), "$class should be queued");
            $this->assertTrue($reflection->getDefaultProperties()['afterCommit'] ?? false, "$class should wait for the database commit");
        }
        $base = new class extends AfisabNotification {};
        $this->assertGreaterThan(1, $base->tries);
        $this->assertSame([60, 300, 900], $base->backoff());
        $this->assertSame(['mail'], $base->via($this->investor));
    }

    public function test_a_delivery_failure_is_logged(): void
    {
        Log::shouldReceive('error')->once()->withArgs(fn ($m) => str_contains($m, 'ProfitPaid') && str_contains($m, 'smtp down'));

        (new ProfitPaid($this->line($this->quarterly)))->failed(new RuntimeException('smtp down'));
    }

    // ---- what each email says ----

    public function test_every_email_renders_in_the_company_template(): void
    {
        $cycle = app(CycleService::class)->open('Nov sesame', Commodity::first(), null, User::factory()->create(['name' => 'Sam Sales'])->assignRole('sales'));
        $payout = CapitalPayout::create(['investment_id' => $this->quarterly->id, 'amount' => 1_000_000_00, 'status' => 'paid', 'prepared_by' => $this->accounts->id, 'payment_reference' => 'CAP-REF-9']);
        $run = DistributionRun::create(['period' => '2026-11', 'cutoff_date' => '2026-11-27', 'pay_by' => '2026-11-30', 'status' => DistributionRun::CALCULATED, 'total_investor' => 1]);

        $cases = [
            [new PaymentConfirmed($this->quarterly), $this->investor, ['We have confirmed your payment', 'AFC-2026-']],
            [new MaturityApproaching($this->quarterly), $this->investor, ['matures on', 'Please make sure your bank details']],
            [new ProfitPaid($this->line($this->quarterly)), $this->investor, ['₦250,000.00', 'NIP-777', 'Returns are a share']],
            [new CapitalPaid($payout), $this->investor, ['₦1,000,000.00', 'CAP-REF-9']],
            [new CapitalPayoutReady($payout), $this->director, ['is ready for your approval']],
            [new CycleAwaitingApproval($cycle), $this->director, ['Sam Sales proposed the cycle', 'Nov sesame']],
            [new DistributionNotice($run, 'Ready for approval', 'Please review.'), $this->director, ['Please review.']],
            [new TestEmail, $this->director, ['Mail is working']],
        ];

        $missing = [];
        foreach ($cases as [$notification, $to, $mustContain]) {
            $html = (string) $notification->toMail($to)->render();
            $name = class_basename($notification);
            $this->assertStringContainsString('AFISAB NIG. LTD', $html, "$name: header or footer shows the company");
            $this->assertStringContainsString('not guaranteed', $html, "$name: carries the risk disclaimer");
            $this->assertStringContainsString('#08532a', $html, "$name: uses the brand colour");
            $this->assertStringNotContainsString('Laravel', $html, "$name: no framework branding left");
            foreach ($mustContain as $text) {
                if (! str_contains($html, $text)) {
                    $missing[] = "$name is missing: $text";
                }
            }
        }
        $this->assertSame([], $missing, 'Expected phrases are missing from the emails');
    }

    public function test_the_built_in_verification_and_reset_emails_use_the_same_template(): void
    {
        $html = (string) (new ResetPassword('token123'))->toMail($this->investor)->render();

        $this->assertStringContainsString('AFISAB NIG. LTD', $html);
        $this->assertStringContainsString('#08532a', $html);
        $this->assertStringNotContainsString('Laravel', $html);
    }

    public function test_the_profit_email_explains_a_quarterly_payment_but_not_a_monthly_one(): void
    {
        $quarterly = (string) (new ProfitPaid($this->line($this->quarterly)))->toMail($this->investor)->render();
        $this->assertStringContainsString('Quarterly payment', $quarterly);
        $this->assertStringContainsString('21 Jul - 21 Oct 2026', $quarterly);

        $monthly = (string) (new ProfitPaid($this->line($this->monthly, '2026-10-02', '2026-10-21')))->toMail($this->monthly->user)->render();
        $this->assertStringNotContainsString('covering trading profit', $monthly);
    }

    public function test_the_welcome_email_states_the_chosen_frequency_and_first_payment_date(): void
    {
        $html = (string) (new PaymentConfirmed($this->quarterly))->toMail($this->investor)->render();

        $this->assertStringContainsString('Quarterly', $html);
        $this->assertStringContainsString('every 3 months', $html);
        $this->assertStringContainsString('payment is due by 31 Jan 2027', $html);
    }

    public function test_the_maturity_email_mentions_final_settlement_only_for_deferred_profit(): void
    {
        $this->assertStringContainsString('settled in the distribution for the month of maturity', (string) (new MaturityApproaching($this->quarterly))->toMail($this->investor)->render());
        $this->assertStringNotContainsString('settled in the distribution', (string) (new MaturityApproaching($this->monthly))->toMail($this->monthly->user)->render());
    }

    // ---- who receives them ----

    public function test_staff_emails_skip_disabled_accounts_and_the_person_who_made_the_request(): void
    {
        $disabled = User::factory()->create()->assignRole('super-admin');
        $disabled->forceFill(['disabled_at' => now()])->save();
        $sales = User::factory()->create()->assignRole('sales');
        Notification::fake();

        app(CycleService::class)->open('Cycle', Commodity::first(), null, $sales);
        Notification::assertSentTo($this->director, CycleAwaitingApproval::class);
        Notification::assertNotSentTo($disabled, CycleAwaitingApproval::class);

        // A Director who prepares a capital payout is not asked to approve it; the other Director is.
        $other = User::factory()->create()->assignRole('super-admin');
        $this->quarterly->update(['status' => \App\Enums\InvestmentStatus::Matured]);
        app(CapitalPayoutService::class)->prepare($this->quarterly, $this->director);
        Notification::assertNotSentTo($this->director, CapitalPayoutReady::class);
        Notification::assertSentTo($other, CapitalPayoutReady::class);
        Notification::assertNotSentTo($disabled, CapitalPayoutReady::class);
    }

    public function test_the_person_who_calculated_a_run_is_not_asked_to_approve_it(): void
    {
        $other = User::factory()->create()->assignRole('super-admin');
        Notification::fake();

        app(DistributionService::class)->calculate(Carbon::parse('2026-10-01'), $this->director);

        Notification::assertNotSentTo($this->director, DistributionNotice::class);
        Notification::assertSentTo($other, DistributionNotice::class);
    }

    // ---- real delivery through the mailer ----

    public function test_a_notification_is_actually_sent_through_the_mailer(): void
    {
        $this->investor->notify(new ProfitPaid($this->line($this->quarterly)));

        // (setUp already sent two welcome emails through the same mailer.)
        $messages = array_values(array_filter($this->sentMail(), fn ($m) => $m->getSubject() === 'Your AFISAB profit share has been paid'));
        $this->assertCount(1, $messages);
        $this->assertSame($this->investor->email, $messages[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('NIP-777', $messages[0]->getHtmlBody());
    }

    public function test_the_test_mail_command(): void
    {
        $this->artisan('afisab:test-mail', ['to' => 'not-an-email'])->expectsOutputToContain('not a valid email')->assertFailed();

        $this->artisan('afisab:test-mail', ['to' => 'boss@example.com'])->expectsOutputToContain('Sent to boss@example.com')->assertSuccessful();

        $sent = collect($this->sentMail())->first(fn ($m) => $m->getSubject() === 'AFISAB test email');
        $this->assertNotNull($sent);
        $this->assertSame('boss@example.com', $sent->getTo()[0]->getAddress());
    }

    // ---- knowing when email is not getting out ----

    public function test_failed_emails_raise_a_warning_that_tells_staff_what_to_do(): void
    {
        $this->assertNull(QueueHealth::warning());

        DB::table('failed_jobs')->insert(['uuid' => 'abc-123', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'smtp refused', 'failed_at' => now()]);

        $warning = QueueHealth::warning();
        $this->assertStringContainsString('1 email could not be delivered', $warning);
        $this->assertStringContainsString('afisab:test-mail', $warning);

        $this->actingAs($this->accounts)->get(route('admin.dashboard'))->assertSee('could not be delivered');
        $this->actingAs(User::factory()->create()->assignRole('sales'))->get(route('admin.dashboard'))->assertDontSee('could not be delivered');
    }

    public function test_emails_stuck_in_the_queue_are_flagged_only_when_they_are_old(): void
    {
        config(['queue.default' => 'database']);
        $job = fn (int $minutesAgo) => DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes($minutesAgo)->timestamp, 'created_at' => now()->subMinutes($minutesAgo)->timestamp]);

        $job(2);
        $this->assertNull(QueueHealth::warning(), 'a fresh job is normal');

        $job(30);
        $this->assertSame(30, QueueHealth::oldestWaitingMinutes());
        $this->assertStringContainsString('nothing is sending them', QueueHealth::warning());
    }

    public function test_preflight_flags_a_synchronous_queue_and_failed_emails(): void
    {
        config(['queue.default' => 'sync']);
        $this->artisan('afisab:preflight')->expectsOutputToContain('Emails are queued')->run();

        config(['queue.default' => 'database']);
        DB::table('failed_jobs')->insert(['uuid' => 'abc-456', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);
        \Illuminate\Support\Facades\Artisan::call('afisab:preflight');
        $out = \Illuminate\Support\Facades\Artisan::output();

        $this->assertMatchesRegularExpression('/ok\s*\|\s*Emails are queued/', $out);
        $this->assertMatchesRegularExpression('/(warn|WARN)\s*\|\s*No emails have failed to send/', $out);
    }
}
