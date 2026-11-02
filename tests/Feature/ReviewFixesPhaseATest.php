<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProfitFrequency;
use App\Models\Enquiry;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fixes from the site review, phase A: wording and small screen problems. */
class ReviewFixesPhaseATest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-02 10:00'));
    }

    private function investor(): User
    {
        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create(['phone' => '08031234567', 'kyc_status' => 'approved', 'address' => 'Abuja', 'date_of_birth' => '1980-01-01', 'nin' => (string) random_int(10_000_000_000, 99_999_999_999)]);

        return $user;
    }

    private function awaiting(?User $user = null): Payment
    {
        $user ??= $this->investor();
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '6-month')->first(), 1_000_000_00, ProfitFrequency::Monthly, ['ip' => '1.1.1.1']);

        return app(PaymentService::class)->submitTransfer($investment, UploadedFile::fake()->image('r.png'));
    }

    public function test_the_dashboard_says_the_27th_of_each_month_without_naming_a_month(): void
    {
        $user = $this->investor();
        $page = $this->actingAs($user)->get(route('investor.dashboard'))->assertOk();

        $page->assertSee('<strong>27th</strong> of each month', false)->assertDontSee('27th of November')->assertDontSee('27th of December');
    }

    public function test_the_sign_in_page_says_it_is_for_investors_and_staff(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Investor &amp; staff sign in', false);
    }

    public function test_enquiry_name_and_company_are_on_separate_lines(): void
    {
        $e = Enquiry::create(['type' => 'quote', 'name' => 'Musa Bello', 'company' => 'Bello Feeds Ltd', 'email' => 'm@example.com', 'phone' => '08030000000', 'message' => 'Hi', 'consented_at' => now(), 'status' => 'new']);
        $director = User::factory()->create()->assignRole('super-admin');

        $html = $this->actingAs($director)->get(route('admin.enquiries'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#Musa Bello</a></div>\s*<div[^>]*>Bello Feeds Ltd</div>#', $html);
    }

    public function test_the_money_received_date_is_required_when_confirming(): void
    {
        $payment = $this->awaiting();
        $accounts = User::factory()->create()->assignRole('accounts');

        $this->actingAs($accounts)->post(route('admin.payments.confirm', $payment))->assertSessionHasErrors(['received_on' => 'Please enter the date the money was received.']);
        $this->assertSame(PaymentStatus::AwaitingConfirmation, $payment->fresh()->status);

        $this->actingAs($accounts)->get(route('admin.payments'))->assertSee('name="received_on"', false)->assertSee('required', false);
        $this->actingAs($accounts)->post(route('admin.payments.confirm', $payment), ['received_on' => '2026-11-02'])->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Confirmed, $payment->fresh()->status);
    }

    public function test_confirming_twice_gives_a_clear_message_not_a_blank_error_page(): void
    {
        $payment = $this->awaiting();
        $accounts = User::factory()->create()->assignRole('accounts');
        $this->actingAs($accounts)->post(route('admin.payments.confirm', $payment), ['received_on' => '2026-11-02']);

        $again = $this->actingAs($accounts)->post(route('admin.payments.confirm', $payment), ['received_on' => '2026-11-02']);

        $again->assertRedirect()->assertSessionHas('status', "Payment {$payment->reference} is already confirmed.");
    }

    public function test_confirming_a_payment_with_no_receipt_explains_why(): void
    {
        $user = $this->investor();
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '6-month')->first(), 1_000_000_00, ProfitFrequency::Monthly, ['ip' => '1.1.1.1']);
        $empty = app(PaymentService::class)->startTransfer($investment);
        $accounts = User::factory()->create()->assignRole('accounts');

        $this->actingAs($accounts)->post(route('admin.payments.confirm', $empty), ['received_on' => '2026-11-02'])->assertRedirect()->assertSessionHasErrors('payment');
        $this->assertSame(PaymentStatus::Pending, $empty->fresh()->status);
    }

    public function test_the_person_who_proposed_a_cycle_does_not_get_an_approve_button(): void
    {
        $proposer = User::factory()->create()->assignRole('super-admin');
        $other = User::factory()->create()->assignRole('super-admin');
        $cycle = app(\App\Services\Trading\CycleService::class)->open('Nov sesame', \App\Models\Commodity::where('name', 'Sesame Seeds')->first(), null, $proposer, 'none', null, null, false);

        $this->actingAs($proposer)->get(route('admin.trading.cycles.show', $cycle))->assertOk()
            ->assertSee('You proposed this cycle, so another Director must approve or reject it.')->assertDontSee('Approve cycle');
        $this->actingAs($other)->get(route('admin.trading.cycles.show', $cycle))->assertOk()->assertSee('Approve cycle')->assertDontSee('You proposed this cycle');
    }

    public function test_the_email_warning_links_to_the_queue_only_for_the_super_admin(): void
    {
        config(['queue.default' => 'database']);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(30)->timestamp, 'created_at' => now()->subMinutes(30)->timestamp]);

        $accounts = User::factory()->create()->assignRole('accounts');
        $this->actingAs($accounts)->get(route('admin.dashboard'))->assertSee('Emails are waiting')->assertSee('Please tell the Super Admin.')->assertDontSee('Open the job queue');

        $director = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($director)->get(route('admin.dashboard'))->assertSee('Emails are waiting')->assertSee('Open the job queue')->assertDontSee('Please tell the Super Admin.');
    }

    public function test_error_messages_are_in_plain_english(): void
    {
        $user = $this->investor();
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '6-month')->first(), 1_000_000_00, ProfitFrequency::Monthly, ['ip' => '1.1.1.1']);

        $this->actingAs($user)->post(route('investor.payments.transfer', $investment), ['proof' => UploadedFile::fake()->create('x.exe', 10, 'application/octet-stream')])
            ->assertSessionHasErrors(['proof' => 'Please upload a JPG, PNG or PDF of your receipt.']);
        $this->actingAs($user)->post(route('investor.payments.transfer', $investment))->assertSessionHasErrors(['proof' => 'Please attach your transfer receipt.']);
        $this->actingAs($user)->post(route('investor.investments.store'), ['investment_plan_id' => 99999, 'amount' => '500,000', 'accept_terms' => '1'])
            ->assertSessionHasErrors(['investment_plan_id' => 'That plan is not available. Please choose one of the plans shown.']);

        foreach (['The proof field', 'selected investment plan id', 'must be a file of type'] as $jargon) {
            $this->assertStringNotContainsString($jargon, implode(' ', collect(session('errors')?->all() ?? [])->all()));
        }
    }
}
