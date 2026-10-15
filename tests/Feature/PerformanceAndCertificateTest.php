<?php

namespace Tests\Feature;

use App\Enums\InvestmentStatus;
use App\Enums\KycStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\User;
use App\Notifications\MaturityApproaching;
use App\Notifications\PaymentConfirmed;
use App\Services\MaturityService;
use App\Services\Payments\PaymentService;
use App\Services\Performance\PerformanceService;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PerformanceAndCertificateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function activeInvestment(int $amount = 1_000_000_00, string $name = 'Ada Obi'): Investment
    {
        $user = User::factory()->create(['name' => $name])->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => KycStatus::Approved]);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '3-month')->first(), $amount);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh();
    }

    // ---- performance entries ----

    public function test_cycle_profit_is_shared_pro_rata_and_sums_exactly(): void
    {
        $a = $this->activeInvestment(1_000_000_00);
        $b = $this->activeInvestment(2_000_000_00);
        $c = $this->activeInvestment(500_000_00);

        // 100,001 kobo over capital 1M / 2M / 0.5M does not divide evenly.
        $entries = app(PerformanceService::class)->recordCycleResult('CYC-1', [
            $a->id => 1_000_000_00, $b->id => 2_000_000_00, $c->id => 500_000_00,
        ], 100_001);

        $this->assertSame(100_001, $entries->sum('net_profit'));
    }

    public function test_recording_a_result_does_not_credit_investors_by_itself(): void
    {
        $investment = $this->activeInvestment();
        $service = app(PerformanceService::class);

        $service->recordCycleResult('CYC-1', [$investment->id => 1_000_000_00], 200_000_00);

        $position = $service->position($investment);
        $this->assertSame(1_000_000_00, $position['current_value']);
        $this->assertSame(0, $position['earned']);
        $this->assertSame(1, $position['pending_entries']);
    }

    public function test_recording_the_same_cycle_twice_does_not_duplicate(): void
    {
        $investment = $this->activeInvestment();
        $service = app(PerformanceService::class);

        $service->recordCycleResult('CYC-1', [$investment->id => 1_000_000_00], 10_000_00);
        $service->recordCycleResult('CYC-1', [$investment->id => 1_000_000_00], 10_000_00);

        $this->assertSame(1, $investment->performanceEntries()->count());
    }

    public function test_cannot_allocate_more_than_the_policy_or_to_inactive_policies(): void
    {
        $investment = $this->activeInvestment();

        try {
            app(PerformanceService::class)->recordCycleResult('X', [$investment->id => 1_000_000_01], 1);
            $this->fail('Expected over-allocation to be rejected');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }

        $investment->update(['status' => InvestmentStatus::Cancelled]);
        $this->expectException(BusinessRuleException::class);
        app(PerformanceService::class)->recordCycleResult('Y', [$investment->id => 500_000_00], 1);
    }

    // ---- certificates and verification ----

    public function test_owner_can_download_certificate_pdf_and_statement(): void
    {
        $investment = $this->activeInvestment();

        $cert = $this->actingAs($investment->user)->get(route('documents.certificate', $investment));
        $cert->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $cert->getContent());

        $statement = $this->actingAs($investment->user)->get(route('documents.statement', $investment));
        $statement->assertOk();
        $this->assertStringStartsWith('%PDF', $statement->getContent());
    }

    public function test_other_investors_cannot_download_documents_but_compliance_can(): void
    {
        $investment = $this->activeInvestment();
        $stranger = User::factory()->create()->assignRole('investor');
        $compliance = User::factory()->create()->assignRole('compliance');

        $this->actingAs($stranger)->get(route('documents.certificate', $investment))->assertNotFound();
        $this->actingAs($stranger)->get(route('documents.statement', $investment))->assertNotFound();
        $this->actingAs($compliance)->get(route('documents.certificate', $investment))->assertOk();
    }

    public function test_certificate_is_not_available_before_payment(): void
    {
        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => KycStatus::Approved]);
        $pending = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::first(), 500_000_00);

        $this->actingAs($user)->get(route('documents.certificate', $pending))->assertNotFound();
    }

    public function test_public_verification_shows_masked_holder_only(): void
    {
        $investment = $this->activeInvestment(name: 'Adaeze Okonkwo');

        $this->get(route('verify.show', $investment->certificate_token))
            ->assertOk()
            ->assertSee($investment->certificate_number)
            ->assertSee('A*** O***', false) // masked
            ->assertDontSee('Adaeze')
            ->assertDontSee('Okonkwo')
            ->assertDontSee($investment->user->email);
    }

    public function test_unknown_verification_code_is_reported_not_found(): void
    {
        $this->get(route('verify.show', 'doesnotexist'))->assertOk()->assertSee('No certificate found');
    }

    public function test_verification_form_redirects_to_the_token_page(): void
    {
        $investment = $this->activeInvestment();

        $this->post(route('verify.lookup'), ['code' => '  '.strtoupper($investment->certificate_token).' '])
            ->assertRedirect(route('verify.show', $investment->certificate_token));
    }

    public function test_investor_pages_render_with_position_and_certificate_links(): void
    {
        $investment = $this->activeInvestment();
        app(PerformanceService::class)->recordCycleResult('CYC-1', [$investment->id => 1_000_000_00], 10_000_00);

        $this->actingAs($investment->user)->get(route('investor.dashboard'))->assertOk()->assertSee('Current value');
        $this->actingAs($investment->user)->get(route('investor.investments.show', $investment))
            ->assertOk()->assertSee('Download certificate')->assertSee('Next monthly calculation');
    }

    // ---- notifications and maturity ----

    public function test_confirming_payment_notifies_the_investor(): void
    {
        Notification::fake();
        $investment = $this->activeInvestment();

        Notification::assertSentTo($investment->user, PaymentConfirmed::class);
    }

    public function test_maturity_job_reminds_once_and_marks_matured(): void
    {
        $investment = $this->activeInvestment(); // 3-month plan
        Notification::fake();

        // Not yet within the reminder window.
        $this->assertSame(['reminded' => 0, 'matured' => 0], app(MaturityService::class)->process());

        $this->travelTo(now()->addMonths(3)->subDays(10));
        $this->assertSame(['reminded' => 1, 'matured' => 0], app(MaturityService::class)->process());
        $this->assertSame(['reminded' => 0, 'matured' => 0], app(MaturityService::class)->process()); // only once
        Notification::assertSentToTimes($investment->user, MaturityApproaching::class, 1);

        $this->travelTo(now()->addMonths(3)->addDay());
        $this->assertSame(1, app(MaturityService::class)->process()['matured']);
        $this->assertSame(InvestmentStatus::Matured, $investment->fresh()->status);
    }
}
