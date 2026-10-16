<?php

namespace Tests\Feature;

use App\Enums\InvestmentStatus;
use App\Models\InvestmentPlan;
use App\Models\Payment;
use App\Models\PaystackSetting;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PaystackSettings;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PaystackManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private array $keys = ['secret_key' => 'sk_test_abcdef123456', 'public_key' => 'pk_test_abcdef654321', 'enabled' => '1', 'password' => 'password'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['services.paystack.secret_key' => '', 'services.paystack.public_key' => '']);
        $this->director = User::factory()->create()->assignRole('super-admin');
    }

    private function subscribe()
    {
        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);

        return app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '3-month')->first(), 1_000_000_00);
    }

    public function test_only_the_super_admin_can_see_or_change_paystack_settings(): void
    {
        $accounts = User::factory()->create()->assignRole('accounts');
        $investor = User::factory()->create()->assignRole('investor');
        $payment = Payment::create(['investment_id' => $this->subscribe()->id, 'method' => 'paystack', 'amount' => 1, 'status' => 'pending', 'reference' => 'PAY-X']);

        foreach ([$accounts, $investor] as $user) {
            $this->actingAs($user)->get(route('admin.paystack'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.paystack.save'), $this->keys)->assertForbidden();
            $this->actingAs($user)->post(route('admin.paystack.test'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.paystack.reverify', $payment))->assertForbidden();
        }
        $this->assertSame(0, PaystackSetting::count());
        $this->actingAs($this->director)->get(route('admin.paystack'))->assertOk()->assertSee('Not configured')->assertSee('/webhooks/paystack');
    }

    public function test_keys_are_saved_encrypted_shown_masked_and_never_logged(): void
    {
        $this->actingAs($this->director)->post(route('admin.paystack.save'), $this->keys)->assertSessionHasNoErrors();

        $settings = app(PaystackSettings::class);
        $this->assertSame('sk_test_abcdef123456', $settings->secret());
        $this->assertSame('test', $settings->mode());
        $this->assertSame('database', $settings->source());

        $raw = DB::table('paystack_settings')->first();
        $this->assertStringNotContainsString('abcdef123456', $raw->secret_key);
        $this->assertStringNotContainsString('abcdef654321', $raw->public_key);

        $page = $this->actingAs($this->director)->get(route('admin.paystack'))->assertOk();
        $page->assertSee('sk_test_••••3456')->assertSee('TEST')->assertDontSee('sk_test_abcdef123456');

        $log = Activity::where('description', 'Paystack settings changed')->firstOrFail();
        $this->assertStringNotContainsString('abcdef123456', json_encode($log->properties));
        $this->assertSame('sk_test_••••3456', $log->properties['after']['secret']);
    }

    public function test_blank_key_keeps_the_existing_one_and_a_password_is_required(): void
    {
        $this->actingAs($this->director)->post(route('admin.paystack.save'), $this->keys);

        $this->actingAs($this->director)->post(route('admin.paystack.save'), ['secret_key' => '', 'public_key' => '', 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($this->director)->post(route('admin.paystack.save'), ['secret_key' => '', 'public_key' => '', 'password' => 'password'])->assertSessionHasNoErrors();

        $this->assertSame('sk_test_abcdef123456', app(PaystackSettings::class)->secret());
        $this->assertFalse(app(PaystackSettings::class)->enabled(), 'unticked box switches online payments off');
    }

    public function test_key_format_and_mode_mismatch_are_rejected(): void
    {
        $this->actingAs($this->director)->post(route('admin.paystack.save'), ['secret_key' => 'not-a-key'] + $this->keys)->assertSessionHasErrors('secret_key');
        $this->actingAs($this->director)->post(route('admin.paystack.save'), ['public_key' => 'sk_test_oops'] + $this->keys)->assertSessionHasErrors('public_key');
        $this->actingAs($this->director)->post(route('admin.paystack.save'), ['secret_key' => 'sk_live_abcdef123456'] + $this->keys)->assertSessionHasErrors('secret_key'); // live secret + test public
        $this->actingAs($this->director)->post(route('admin.paystack.save'), ['secret_key' => '', 'public_key' => ''] + $this->keys)->assertSessionHasErrors('secret_key'); // first save needs a secret
        $this->assertSame(0, PaystackSetting::count());
    }

    public function test_saved_settings_override_env_and_drive_the_gateway_calls(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_fromenv']);
        $this->actingAs($this->director)->post(route('admin.paystack.save'), $this->keys);

        Http::fake(['*/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']])]);
        app(PaymentService::class)->startPaystack($this->subscribe());

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk_test_abcdef123456'));
    }

    public function test_switching_off_blocks_new_online_payments_but_not_settling_ones_in_flight(): void
    {
        $this->actingAs($this->director)->post(route('admin.paystack.save'), $this->keys);
        $investment = $this->subscribe();
        Http::fake([
            '*/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']]),
            '*/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => 1_000_000_00, 'currency' => 'NGN', 'id' => 99]]),
        ]);
        app(PaymentService::class)->startPaystack($investment);
        $payment = Payment::firstOrFail();

        $this->actingAs($this->director)->post(route('admin.paystack.save'), ['enabled' => '0'] + array_diff_key($this->keys, ['enabled' => 1]));
        $this->assertFalse(app(PaystackSettings::class)->enabled());

        $other = $this->subscribe();
        try {
            app(PaymentService::class)->startPaystack($other);
            $this->fail('new online payment allowed while switched off');
        } catch (\App\Exceptions\BusinessRuleException) {
        }
        $this->assertSame(1, Payment::count(), 'no stray payment row was created');

        $investor = $other->user;
        $this->actingAs($investor)->get(route('investor.investments.show', $other))->assertSee('Online payment is not available right now');

        app(PaymentService::class)->settlePaystack($payment->reference); // webhook/callback still settles
        $this->assertSame(InvestmentStatus::Active, $investment->fresh()->status);
    }

    public function test_connection_test_records_the_result(): void
    {
        $this->actingAs($this->director)->post(route('admin.paystack.save'), $this->keys);

        Http::swap(new Factory);
        Http::fake(['*/transaction*' => Http::response(['status' => true, 'data' => []])]);
        $this->actingAs($this->director)->post(route('admin.paystack.test'))->assertSessionHas('status');
        $this->assertTrue(PaystackSetting::first()->last_test_ok);

        Http::swap(new Factory);
        Http::fake(['*/transaction*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);
        $this->actingAs($this->director)->post(route('admin.paystack.test'))->assertSessionHasErrors('paystack');
        $this->assertFalse(PaystackSetting::first()->last_test_ok);
        $this->assertSame('Paystack rejected the secret key.', PaystackSetting::first()->last_test_message);
    }

    public function test_a_missed_payment_can_be_rechecked_and_settled(): void
    {
        $this->actingAs($this->director)->post(route('admin.paystack.save'), $this->keys);
        $investment = $this->subscribe();
        Http::swap(new Factory);
        Http::fake(['*/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']])]);
        app(PaymentService::class)->startPaystack($investment);
        $payment = Payment::firstOrFail();

        Http::swap(new Factory);
        Http::fake(['*/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'abandoned', 'amount' => 0, 'currency' => 'NGN']])]);
        $this->actingAs($this->director)->post(route('admin.paystack.reverify', $payment))->assertSessionHas('status');
        $this->assertSame(InvestmentStatus::PendingPayment, $investment->fresh()->status);

        Http::swap(new Factory);
        Http::fake(['*/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => 1_000_000_00, 'currency' => 'NGN', 'id' => 7]])]);
        $this->actingAs($this->director)->post(route('admin.paystack.reverify', $payment))->assertSessionHas('status');
        $this->assertSame(InvestmentStatus::Active, $investment->fresh()->status);

        $this->actingAs($this->director)->post(route('admin.paystack.reverify', $payment))->assertSessionHasErrors('paystack'); // no longer pending
    }

    public function test_webhook_signature_uses_the_saved_secret(): void
    {
        $this->actingAs($this->director)->post(route('admin.paystack.save'), $this->keys);
        $body = json_encode(['event' => 'ping']);

        $this->call('POST', '/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_fromenv')], $body)->assertStatus(401);
        $this->call('POST', '/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_abcdef123456')], $body)->assertOk();
    }
}
