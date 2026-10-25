<?php

namespace Tests\Feature;

use App\Enums\ProfitFrequency;
use App\Exceptions\BusinessRuleException;
use App\Models\InvestmentPlan;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PaystackClient;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** When Paystack refuses to start a payment, investors get a generic message and the super-admin gets the real reason. */
class PaystackDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        Cache::forget(PaystackClient::LAST_FAILURE);
    }

    private function start(): void
    {
        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '6-month')->first(), 1_000_000_00, ProfitFrequency::Monthly);
        app(PaymentService::class)->startPaystack($investment);
    }

    public function test_a_rejected_key_is_recorded_with_what_to_check_and_the_investor_sees_only_the_generic_message(): void
    {
        Log::spy();
        Http::fake(['*/transaction/initialize' => Http::response(['status' => false, 'message' => 'Invalid key', 'code' => 'secret_key_invalid'], 401)]);

        try {
            $this->start();
            $this->fail('expected a failure');
        } catch (BusinessRuleException $e) {
            $this->assertSame('Could not start the online payment. Please try again or use bank transfer.', $e->getMessage());
        }

        $failure = PaystackClient::lastFailure();
        $this->assertSame([401, 'Invalid key', 'secret_key_invalid'], [$failure['http'], $failure['message'], $failure['code']]);
        $this->assertStringContainsString('rejected the secret key', $failure['hint']);
        $this->assertStringNotContainsString('sk_test_secret', json_encode($failure));
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($m, $ctx) => $m === 'Paystack could not start a payment' && $ctx['http'] === 401);
    }

    public function test_a_network_failure_is_a_clean_message_not_a_server_error_and_is_recorded(): void
    {
        Http::fake(['*/transaction/initialize' => fn () => throw new ConnectionException('cURL error 60: SSL certificate problem')]);

        $this->expectException(BusinessRuleException::class);
        try {
            $this->start();
        } finally {
            $failure = PaystackClient::lastFailure();
            $this->assertNull($failure['http']);
            $this->assertStringContainsString('could not connect to Paystack', $failure['hint']);
        }
    }

    public function test_the_hints_cover_the_common_causes(): void
    {
        $this->assertStringContainsString('NGN', PaystackClient::hint(400, 'Currency not supported by merchant', null));
        $this->assertStringContainsString('email', PaystackClient::hint(400, 'Invalid Email Address Passed', null));
        $this->assertStringContainsString('amount', PaystackClient::hint(400, 'Invalid Amount Sent', null));
        $this->assertStringContainsString('on its side', PaystackClient::hint(503, 'Service unavailable', null));
    }

    public function test_the_super_admin_sees_the_last_failure_on_the_paystack_page_and_nobody_else_can(): void
    {
        Http::fake(['*/transaction/initialize' => Http::response(['status' => false, 'message' => 'Invalid key', 'code' => 'secret_key_invalid'], 401)]);
        try {
            $this->start();
        } catch (BusinessRuleException) {
        }

        $director = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($director)->get(route('admin.paystack'))->assertOk()
            ->assertSee('could not be started')->assertSee('Invalid key')->assertSee('secret_key_invalid')->assertSee('What to check');
        $this->actingAs(User::factory()->create()->assignRole('accounts'))->get(route('admin.paystack'))->assertForbidden();
    }

    public function test_the_card_is_absent_when_nothing_has_failed(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('super-admin'))->get(route('admin.paystack'))->assertOk()->assertDontSee('could not be started');
    }
}
