<?php

namespace Tests\Feature;

use App\Enums\InvestmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProfitFrequency;
use App\Exceptions\BusinessRuleException;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fixes from the site review, phase C: unpaid subscription requests no longer stay forever. */
class ReviewFixesPhaseCTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(DatabaseSeeder::class);
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        $this->travelTo(Carbon::parse('2026-11-02 10:00'));
    }

    private function request(?User $user = null, int $ageDays = 10): Investment
    {
        $user ??= User::factory()->create()->assignRole('investor');
        $user->investorProfile()->firstOrCreate([], ['phone' => '0800', 'kyc_status' => 'approved', 'nin' => (string) random_int(10_000_000_000, 99_999_999_999)]);
        $i = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '6-month')->first(), 1_000_000_00, ProfitFrequency::Monthly);
        $i->forceFill(['created_at' => now()->subDays($ageDays)])->save();

        return $i->fresh();
    }

    private function expire(): int
    {
        return app(SubscriptionService::class)->expireUnpaid();
    }

    public function test_an_old_unpaid_request_and_its_empty_attempts_lapse(): void
    {
        $i = $this->request();
        $empty = app(PaymentService::class)->startTransfer($i);
        $card = $i->payments()->create(['method' => PaymentMethod::Paystack, 'amount' => $i->amount, 'status' => PaymentStatus::Pending, 'reference' => 'PAY-OLD'])->forceFill(['created_at' => now()->subDays(9)]);
        $card->save();

        $this->assertSame(1, $this->expire());

        $this->assertSame(InvestmentStatus::Cancelled, $i->fresh()->status);
        $this->assertNotNull($i->fresh()->expired_at);
        $this->assertSame('Expired (unpaid)', $i->fresh()->statusLabel());
        $this->assertSame([PaymentStatus::Expired, PaymentStatus::Expired], [$empty->fresh()->status, $card->fresh()->status]);
        $this->assertDatabaseHas('activity_log', ['description' => 'Unpaid subscription request expired']);
        $this->assertSame(0, $this->expire(), 'a second run finds nothing');
    }

    public function test_recent_paid_and_in_progress_requests_are_left_alone(): void
    {
        $recent = $this->request(ageDays: 2);

        $withReceipt = $this->request();
        app(PaymentService::class)->submitTransfer($withReceipt, UploadedFile::fake()->image('r.png'));

        $cardJustStarted = $this->request();
        $cardJustStarted->payments()->create(['method' => PaymentMethod::Paystack, 'amount' => $cardJustStarted->amount, 'status' => PaymentStatus::Pending, 'reference' => 'PAY-NOW']);

        $active = $this->request();
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($active));

        $this->assertSame(0, $this->expire());
        foreach ([$recent, $withReceipt, $cardJustStarted] as $i) {
            $this->assertSame(InvestmentStatus::PendingPayment, $i->fresh()->status);
        }
        $this->assertSame(InvestmentStatus::Active, $active->fresh()->status);
    }

    public function test_the_number_of_days_is_a_setting(): void
    {
        $i = $this->request(ageDays: 4);
        $this->assertSame(0, $this->expire());

        config(['afisab.subscription.expire_unpaid_after_days' => 3]);
        $this->assertSame(1, $this->expire());
        $this->assertSame(InvestmentStatus::Cancelled, $i->fresh()->status);
    }

    public function test_a_payment_that_arrives_after_expiry_is_still_honoured(): void
    {
        $i = $this->request();
        Http::fake([
            '*/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']]),
            '*/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => 1_000_000_00, 'currency' => 'NGN', 'id' => 777]]),
        ]);
        app(PaymentService::class)->startPaystack($i);
        $payment = Payment::sole();
        $payment->forceFill(['created_at' => now()->subDays(9)])->save();
        $this->assertSame(1, $this->expire());
        $this->assertSame(PaymentStatus::Expired, $payment->fresh()->status);

        app(PaymentService::class)->settlePaystack($payment->reference);

        $this->assertSame(InvestmentStatus::Active, $i->fresh()->status);
        $this->assertNull($i->fresh()->expired_at);
        $this->assertSame(PaymentStatus::Confirmed, $payment->fresh()->status);
        $this->assertDatabaseHas('activity_log', ['description' => 'Expired subscription request revived by a late payment']);
    }

    public function test_an_expired_attempt_that_was_never_paid_stays_expired(): void
    {
        $i = $this->request();
        Http::fake(['*/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'abandoned', 'amount' => 0, 'currency' => 'NGN']])]);
        $payment = $i->payments()->create(['method' => PaymentMethod::Paystack, 'amount' => $i->amount, 'status' => PaymentStatus::Pending, 'reference' => 'PAY-X'])->forceFill(['created_at' => now()->subDays(9)]);
        $payment->save();
        $this->expire();

        app(PaymentService::class)->settlePaystack('PAY-X');

        $this->assertSame(PaymentStatus::Expired, $payment->fresh()->status);
        $this->assertSame(InvestmentStatus::Cancelled, $i->fresh()->status);
    }

    public function test_a_request_the_investor_cancelled_themselves_is_never_revived(): void
    {
        $i = $this->request(ageDays: 1);
        $payment = app(PaymentService::class)->submitTransfer($i, UploadedFile::fake()->image('r.png'));
        $payment->update(['status' => PaymentStatus::Pending]);
        app(SubscriptionService::class)->cancel($i->fresh());

        $this->expectException(BusinessRuleException::class);
        app(PaymentService::class)->confirm($payment->fresh());
    }

    public function test_starting_a_new_card_payment_tidies_older_unfinished_attempts(): void
    {
        $i = $this->request(ageDays: 1);
        Http::fake(['*/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']])]);
        $old = $i->payments()->create(['method' => PaymentMethod::Paystack, 'amount' => $i->amount, 'status' => PaymentStatus::Pending, 'reference' => 'PAY-OLD1'])->forceFill(['created_at' => now()->subHours(3)]);
        $old->save();
        $recent = $i->payments()->create(['method' => PaymentMethod::Paystack, 'amount' => $i->amount, 'status' => PaymentStatus::Pending, 'reference' => 'PAY-RECENT']);

        app(PaymentService::class)->startPaystack($i);

        $this->assertSame(PaymentStatus::Expired, $old->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $recent->fresh()->status, 'one started a moment ago may still be in progress');
        $this->assertSame(3, $i->payments()->count());
    }

    public function test_the_command_is_scheduled_daily_and_the_investor_sees_expired(): void
    {
        $i = $this->request();
        $this->artisan('afisab:expire-unpaid')->expectsOutputToContain('Expired 1 unpaid')->assertSuccessful();

        $this->actingAs($i->user)->get(route('investor.dashboard'))->assertSee('Expired (unpaid)');
        $this->actingAs($i->user)->get(route('investor.investments.show', $i))->assertSee('Expired (unpaid)');
        $this->artisan('schedule:list')->expectsOutputToContain('afisab:expire-unpaid')->assertSuccessful();
    }
}
