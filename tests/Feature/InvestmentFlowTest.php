<?php

namespace Tests\Feature;

use App\Enums\InvestmentStatus;
use App\Enums\KycStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Payments\PaymentService;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvestmentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['services.paystack.secret_key' => 'sk_test_secret']);
    }

    private function investor(KycStatus $kyc = KycStatus::Approved): User
    {
        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create(['phone' => '08000000000', 'kyc_status' => $kyc]);

        return $user;
    }

    private function subscribe(User $user, int $amount = 1_000_000_00, string $slug = '6-month'): Investment
    {
        return app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', $slug)->first(), $amount);
    }

    public function test_registration_creates_an_investor_with_a_profile(): void
    {
        $this->post('/register', [
            'name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone' => '08031234567',
            'password' => 'Str0ngPassword1', 'password_confirmation' => 'Str0ngPassword1',
        ])->assertRedirect();

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('investor'));
        $this->assertSame('08031234567', $user->investorProfile->phone);
    }

    public function test_minimum_amount_and_kyc_are_enforced(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->subscribe($this->investor(), 499_999_99);
    }

    public function test_unverified_investor_cannot_subscribe(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->subscribe($this->investor(KycStatus::Submitted));
    }

    public function test_confirming_a_transfer_activates_policy_and_books_funds(): void
    {
        Storage::fake();
        $user = $this->investor();
        $investment = $this->subscribe($user);
        $payments = app(PaymentService::class);

        $payment = $payments->startTransfer($investment);
        $payments->submitProof($payment, UploadedFile::fake()->image('receipt.png'));
        $this->assertSame(PaymentStatus::AwaitingConfirmation, $payment->fresh()->status);

        $accounts = User::factory()->create()->assignRole('accounts');
        $payments->confirm($payment, $accounts);

        $investment->refresh();
        $this->assertSame(InvestmentStatus::Active, $investment->status);
        $this->assertSame(now()->addMonthsNoOverflow(6)->toDateString(), $investment->maturity_date->toDateString());
        $this->assertStringStartsWith('AFC-', $investment->certificate_number);

        $ledger = app(LedgerService::class);
        $this->assertSame(950_000_00, $ledger->balance(LedgerAccount::MAIN_OPS, $investment));
        $this->assertSame(50_000_00, $ledger->balance(LedgerAccount::RISK_FUND));
        $this->assertSame(1_000_000_00, $ledger->balance(LedgerAccount::INVESTOR_FUNDS, $investment));
    }

    public function test_double_confirmation_does_not_double_book_funds(): void
    {
        $investment = $this->subscribe($this->investor());
        $payments = app(PaymentService::class);
        $payment = $payments->startTransfer($investment);

        $payments->confirm($payment);
        $payments->confirm($payment);

        $this->assertSame(1_000_000_00, app(LedgerService::class)->balance(LedgerAccount::INVESTOR_FUNDS));
    }

    public function test_investor_cannot_see_another_investors_policy(): void
    {
        $investment = $this->subscribe($this->investor());
        $stranger = $this->investor();

        $this->actingAs($stranger)->get(route('investor.investments.show', $investment))->assertNotFound();
        $this->actingAs($investment->user)->get(route('investor.investments.show', $investment))->assertOk();
    }

    public function test_investor_cannot_reach_back_office_or_confirm_payments(): void
    {
        $investment = $this->subscribe($this->investor());
        $payment = app(PaymentService::class)->startTransfer($investment);

        $this->actingAs($investment->user)->get(route('admin.payments'))->assertForbidden();
        $this->actingAs($investment->user)->post(route('admin.payments.confirm', $payment))->assertForbidden();
    }

    public function test_accounts_staff_can_confirm_via_the_back_office(): void
    {
        Storage::fake();
        $investment = $this->subscribe($this->investor());
        $payments = app(PaymentService::class);
        $payment = $payments->startTransfer($investment);
        $payments->submitProof($payment, UploadedFile::fake()->image('r.png'));

        $staff = User::factory()->create()->assignRole('accounts');
        $this->actingAs($staff)->post(route('admin.payments.confirm', $payment))->assertRedirect();

        $this->assertSame(InvestmentStatus::Active, $investment->fresh()->status);
    }

    public function test_paystack_payment_is_verified_with_gateway_before_confirming(): void
    {
        $investment = $this->subscribe($this->investor());

        Http::fake([
            '*/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc']]),
            '*/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => 1_000_000_00, 'currency' => 'NGN', 'id' => 4242]]),
        ]);

        $url = app(PaymentService::class)->startPaystack($investment);
        $this->assertSame('https://checkout.paystack.com/abc', $url);

        $payment = Payment::firstOrFail();
        app(PaymentService::class)->settlePaystack($payment->reference);

        $this->assertSame(InvestmentStatus::Active, $investment->fresh()->status);
        $this->assertSame('4242', $payment->fresh()->gateway_reference);
    }

    public function test_paystack_amount_mismatch_is_rejected(): void
    {
        $investment = $this->subscribe($this->investor());
        Http::fake([
            '*/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://x']]),
            '*/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => 100, 'currency' => 'NGN']]),
        ]);
        app(PaymentService::class)->startPaystack($investment);

        $this->expectException(BusinessRuleException::class);
        app(PaymentService::class)->settlePaystack(Payment::firstOrFail()->reference);
    }

    public function test_webhook_requires_a_valid_signature(): void
    {
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'nope']]);

        $this->call('POST', '/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => 'bad'], $body)
            ->assertStatus(401);

        $sig = hash_hmac('sha512', $body, 'sk_test_secret');
        $this->call('POST', '/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $sig], $body)
            ->assertOk();
    }

    public function test_kyc_submission_and_approval(): void
    {
        Storage::fake();
        $user = $this->investor(KycStatus::Incomplete);

        $this->actingAs($user)->put(route('investor.profile.update'), [
            'phone' => '08031234567', 'date_of_birth' => '1990-01-01', 'address' => '1 Test Street, Abuja',
            'nin' => '12345678901', 'bank_name' => 'Test Bank', 'account_number' => '0123456789',
            'account_name' => $user->name, 'id_document' => UploadedFile::fake()->image('id.jpg'),
        ])->assertSessionHasNoErrors();

        $profile = $user->investorProfile->fresh();
        $this->assertSame(KycStatus::Submitted, $profile->kyc_status);
        $this->assertNotSame('12345678901', $profile->getRawOriginal('nin')); // stored encrypted

        $compliance = User::factory()->create()->assignRole('compliance');
        $this->actingAs($compliance)->post(route('admin.kyc.review', $profile), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame(KycStatus::Approved, $profile->fresh()->kyc_status);
    }
}
