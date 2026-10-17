<?php

namespace Tests\Feature;

use App\Enums\ProfitFrequency;
use App\Models\CapitalPayout;
use App\Models\DistributionLine;
use App\Models\DistributionRun;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class InvestorDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $accounts;

    private User $compliance;

    private User $auditor;

    private User $sales;

    private User $ada;

    private User $bayo;

    private User $chika;

    private Investment $adaPolicy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
        $this->accounts = User::factory()->create()->assignRole('accounts');
        $this->compliance = User::factory()->create()->assignRole('compliance');
        $this->auditor = User::factory()->create()->assignRole('auditor');
        $this->sales = User::factory()->create()->assignRole('sales');

        // Ada: verified, bank details, a quarterly policy that has been paid some profit.
        $this->ada = User::factory()->create(['name' => 'Ada Obi', 'email' => 'ada@example.com'])->assignRole('investor');
        $this->ada->investorProfile()->create([
            'phone' => '08031112222', 'kyc_status' => 'approved', 'bvn' => '12345678901', 'address' => '1 Marina, Lagos',
            'bank_name' => 'Zenith', 'account_number' => '1012345678', 'account_name' => 'Ada Obi', 'id_document_path' => 'kyc/ada.pdf',
            'kyc_reviewed_by' => $this->compliance->id, 'kyc_reviewed_at' => now(),
        ]);
        $this->adaPolicy = $this->subscribe($this->ada, 2_000_000_00, ProfitFrequency::Quarterly);

        // Bayo: submitted KYC, no money in yet. Chika: registered, nothing else.
        $this->bayo = User::factory()->create(['name' => 'Bayo Ade', 'email' => 'bayo@example.com'])->assignRole('investor');
        $this->bayo->investorProfile()->create(['phone' => '08055556666', 'kyc_status' => 'submitted']);
        $this->chika = User::factory()->create(['name' => 'Chika Eze', 'email' => 'chika@example.com'])->assignRole('investor');

        foreach ([['2026-08', DistributionLine::PAID, 1_000_000], ['2026-09', DistributionLine::PAYABLE, 500_000]] as [$period, $status, $share]) {
            $run = DistributionRun::create(['period' => $period, 'cutoff_date' => $period.'-27', 'pay_by' => $period.'-30', 'status' => DistributionRun::PAID]);
            DistributionLine::create([
                'distribution_run_id' => $run->id, 'investment_id' => $this->adaPolicy->id, 'net_profit' => $share * 4, 'retained' => 0, 'distributable' => $share * 4,
                'investor_share' => $share, 'company_share' => 0, 'status' => $status, 'paid_at' => $status === DistributionLine::PAID ? now() : null,
                'payment_reference' => $status === DistributionLine::PAID ? 'NIP-AB12' : null, 'frequency' => 'quarterly',
            ]);
        }
        CapitalPayout::create(['investment_id' => $this->adaPolicy->id, 'amount' => 123_456_00, 'status' => 'prepared', 'prepared_by' => $this->accounts->id]);
    }

    private function subscribe(User $user, int $amount, ProfitFrequency $frequency = ProfitFrequency::Monthly): Investment
    {
        $user->investorProfile?->update(['kyc_status' => 'approved']);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '12-month')->first(), $amount, $frequency);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh();
    }

    // ---- who can see it ----

    public function test_only_staff_with_investors_view_can_open_the_directory(): void
    {
        foreach ([$this->accounts, $this->sales, $this->compliance, $this->auditor] as $staff) {
            $this->actingAs($staff)->get(route('admin.investors'))->assertOk();
            $this->actingAs($staff)->get(route('admin.investors.show', $this->ada))->assertOk();
            $this->actingAs($staff)->get(route('admin.investors.export'))->assertOk();
        }

        $outsiders = [
            User::factory()->create()->assignRole('hr'),
            User::factory()->create()->assignRole('procurement'),
            $this->bayo, // an investor cannot browse other investors
        ];
        foreach ($outsiders as $user) {
            $this->actingAs($user)->get(route('admin.investors'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.investors.show', $this->ada))->assertForbidden();
            $this->actingAs($user)->get(route('admin.investors.export'))->assertForbidden();
        }
    }

    public function test_only_investors_can_be_opened(): void
    {
        $this->actingAs($this->accounts)->get(route('admin.investors.show', $this->sales))->assertNotFound();
    }

    // ---- the list ----

    public function test_the_list_shows_every_investor_with_holdings_and_profit(): void
    {
        $page = $this->actingAs($this->accounts)->get(route('admin.investors'))->assertOk();

        $page->assertSee('Ada Obi')->assertSee('Bayo Ade')->assertSee('Chika Eze');
        $page->assertSee('₦2,000,000.00');       // Ada's active principal
        $page->assertSee('₦10,000.00');          // profit paid (1,000,000 kobo)
        $page->assertSee('₦5,000.00');           // profit payable
        $page->assertSee('KYC awaiting review'); // summary card
        $page->assertDontSee($this->accounts->name . '</a>', false); // staff are not listed
    }

    public function test_search_finds_by_name_email_phone_and_policy_reference(): void
    {
        foreach (['Ada', 'ada@example', '08031112222', $this->adaPolicy->reference] as $term) {
            $this->actingAs($this->accounts)->get(route('admin.investors', ['q' => $term]))->assertOk()->assertSee('Ada Obi')->assertDontSee('Bayo Ade');
        }
        $this->actingAs($this->accounts)->get(route('admin.investors', ['q' => 'nobody-here']))->assertSee('No investors match');
    }

    public function test_search_treats_wildcards_literally(): void
    {
        $this->actingAs($this->accounts)->get(route('admin.investors', ['q' => '%']))->assertSee('No investors match');
        $this->actingAs($this->accounts)->get(route('admin.investors', ['q' => '_']))->assertSee('No investors match');
    }

    public function test_filters(): void
    {
        $kyc = fn (string $status) => $this->actingAs($this->accounts)->get(route('admin.investors', ['kyc' => $status]));
        $kyc('approved')->assertSee('Ada Obi')->assertDontSee('Bayo Ade')->assertDontSee('Chika Eze');
        $kyc('submitted')->assertSee('Bayo Ade')->assertDontSee('Ada Obi');
        $kyc('incomplete')->assertSee('Chika Eze')->assertDontSee('Ada Obi'); // no profile counts as incomplete

        $this->actingAs($this->accounts)->get(route('admin.investors', ['status' => 'active']))->assertSee('Ada Obi')->assertDontSee('Bayo Ade');
        $this->actingAs($this->accounts)->get(route('admin.investors', ['status' => 'none']))->assertSee('Bayo Ade')->assertSee('Chika Eze')->assertDontSee('Ada Obi');
        $this->actingAs($this->accounts)->get(route('admin.investors', ['frequency' => 'quarterly']))->assertSee('Ada Obi')->assertDontSee('Bayo Ade');
        $this->actingAs($this->accounts)->get(route('admin.investors', ['frequency' => 'monthly']))->assertDontSee('Ada Obi');

        $this->actingAs($this->accounts)->get(route('admin.investors', ['kyc' => 'bogus']))->assertSessionHasErrors('kyc');
    }

    public function test_sorting_and_paging(): void
    {
        $this->actingAs($this->accounts)->get(route('admin.investors', ['sort' => 'name']))->assertSeeInOrder(['Ada Obi', 'Bayo Ade', 'Chika Eze']);
        $this->actingAs($this->accounts)->get(route('admin.investors', ['sort' => 'invested']))->assertSeeInOrder(['Ada Obi', 'Bayo Ade']);

        User::factory()->count(30)->create()->each(fn (User $u) => $u->assignRole('investor'));
        $this->actingAs($this->accounts)->get(route('admin.investors'))->assertOk()->assertSee('page=2');
        $this->actingAs($this->accounts)->get(route('admin.investors', ['page' => 2]))->assertOk();
    }

    // ---- one investor ----

    public function test_the_detail_page_shows_the_whole_history(): void
    {
        $page = $this->actingAs($this->accounts)->get(route('admin.investors.show', $this->ada))->assertOk();

        $page->assertSee('Ada Obi')->assertSee('ada@example.com')->assertSee('08031112222')->assertSee('1 Marina, Lagos');
        $page->assertSee('Zenith')->assertSee('1012345678');                                  // where to pay them
        $page->assertSee($this->adaPolicy->reference)->assertSee('Quarterly')->assertSee('₦2,000,000.00');
        $page->assertSee('Payments in')->assertSee('NIP-AB12')->assertSee('Capital returned')->assertSee('₦123,456.00');

        $this->actingAs($this->accounts)->get(route('admin.investors.show', $this->chika))->assertOk()->assertSee('not started their profile')->assertSee('No investments yet');
    }

    public function test_bvn_id_document_and_pdfs_are_limited_to_the_right_roles(): void
    {
        $masked = $this->actingAs($this->accounts)->get(route('admin.investors.show', $this->ada))->assertOk();
        $masked->assertSee('•••••••8901')->assertDontSee('12345678901')->assertDontSee('View ID document')->assertDontSee('Certificate');

        $full = $this->actingAs($this->compliance)->get(route('admin.investors.show', $this->ada))->assertOk();
        $full->assertSee('12345678901')->assertSee('View ID document')->assertSee('Certificate')->assertSee('Statement');
    }

    public function test_the_activity_trail_is_for_auditors_only_and_views_are_recorded_once(): void
    {
        $this->actingAs($this->accounts)->get(route('admin.investors.show', $this->ada))->assertDontSee('Recent activity');
        $this->actingAs($this->accounts)->get(route('admin.investors.show', $this->ada));
        $this->assertSame(1, Activity::where('description', 'Investor record viewed')->where('causer_id', $this->accounts->id)->count(), 'a refresh is not a new view');

        $this->actingAs($this->auditor)->get(route('admin.investors.show', $this->ada))
            ->assertSee('Recent activity')->assertSee('Investment subscription created')->assertSee('Investor record viewed')->assertSee($this->accounts->name);

        $this->travelTo(now()->addHour());
        $this->actingAs($this->accounts)->get(route('admin.investors.show', $this->ada));
        $this->assertSame(2, Activity::where('description', 'Investor record viewed')->where('causer_id', $this->accounts->id)->count());
    }

    // ---- export ----

    public function test_export_respects_filters_is_safe_and_is_audited(): void
    {
        $evil = User::factory()->create(['name' => '=HYPERLINK("http://x")', 'email' => 'evil@example.com'])->assignRole('investor');

        $csv = $this->actingAs($this->accounts)->get(route('admin.investors.export', ['kyc' => 'approved']))->streamedContent();
        $this->assertStringContainsString('Name,Email,Phone,KYC,Policies', $csv);
        $this->assertStringContainsString('"Ada Obi",ada@example.com,08031112222,Approved,1,1,2000000.00,10000.00,5000.00', $csv);
        $this->assertStringNotContainsString('Bayo Ade', $csv);

        $all = $this->actingAs($this->accounts)->get(route('admin.investors.export'))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $all, 'formula injection is neutralised');
        $this->assertStringNotContainsString(',=HYPERLINK', $all);

        $this->assertDatabaseHas('activity_log', ['description' => 'Investor list exported', 'causer_id' => $this->accounts->id]);
    }
}
