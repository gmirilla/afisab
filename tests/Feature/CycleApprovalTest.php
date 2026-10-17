<?php

namespace Tests\Feature;

use App\Enums\InvestmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Commodity;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\Supplier;
use App\Models\TradeCycle;
use App\Models\User;
use App\Notifications\CycleAwaitingApproval;
use App\Services\Payments\PaymentService;
use App\Services\Risk\RiskService;
use App\Services\SubscriptionService;
use App\Services\Trading\CycleService;
use App\Services\Trading\PurchaseService;
use App\Services\Treasury\CapitalPayoutService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CycleApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    private User $procurement;

    private User $director;

    private User $director2;

    private CycleService $cycles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->sales = User::factory()->create()->assignRole('sales');
        $this->procurement = User::factory()->create()->assignRole('procurement');
        $this->director = User::factory()->create()->assignRole('super-admin');
        $this->director2 = User::factory()->create()->assignRole('super-admin');
        $this->cycles = app(CycleService::class);
    }

    private function investment(int $amount, string $name = 'Investor'): Investment
    {
        $user = User::factory()->create(['name' => $name])->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '12-month')->first(), $amount);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh();
    }

    private function commodity(): Commodity
    {
        return Commodity::where('name', 'Sesame Seeds')->first();
    }

    private function propose(string $capital = 'none', ?int $target = null, ?int $buffer = null, bool $include = false, ?User $by = null): TradeCycle
    {
        return $this->cycles->open('Nov sesame', $this->commodity(), null, $by ?? $this->sales, $capital, $target, $buffer, $include);
    }

    // ---- approval ----

    public function test_a_new_cycle_is_pending_and_cannot_trade_until_a_director_approves(): void
    {
        $inv = $this->investment(1_000_000_00);
        $cycle = $this->propose();
        $this->cycles->allocate($cycle, $inv, 900_000_00, $this->sales);
        $supplier = Supplier::create(['name' => 'S', 'type' => 'aggregator', 'is_active' => true]);

        $this->assertSame(TradeCycle::PENDING, $cycle->status);
        try {
            app(PurchaseService::class)->propose($cycle, $supplier, 1_000, 5_000, $this->procurement);
            $this->fail('purchase proposed on a cycle nobody approved');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }

        $this->cycles->approve($cycle, $this->director);

        $this->assertSame(TradeCycle::OPEN, $cycle->fresh()->status);
        $this->assertSame($this->director->id, $cycle->fresh()->approved_by);
        $this->assertNotNull(app(PurchaseService::class)->propose($cycle->fresh(), $supplier, 1_000, 5_000, $this->procurement));
    }

    public function test_approval_needs_a_different_person_and_some_capital(): void
    {
        $inv = $this->investment(1_000_000_00);
        $empty = $this->propose();

        try {
            $this->cycles->approve($empty, $this->director);
            $this->fail('approved a cycle with no capital');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('no investor capital', $e->getMessage());
        }

        $mine = $this->propose(by: $this->director);
        $this->cycles->allocate($mine, $inv, 500_000_00, $this->director);
        try {
            $this->cycles->approve($mine, $this->director);
            $this->fail('director approved their own proposal');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('someone other than', $e->getMessage());
        }
        $this->assertSame(TradeCycle::OPEN, $this->cycles->approve($mine, $this->director2)->status);
    }

    public function test_rejecting_releases_the_capital_and_is_final(): void
    {
        $inv = $this->investment(1_000_000_00);
        $cycle = $this->propose('all');
        $this->assertSame(0, $this->cycles->unallocated($inv));

        $this->cycles->reject($cycle, $this->director, 'Wrong commodity');

        $this->assertSame(TradeCycle::CANCELLED, $cycle->fresh()->status);
        $this->assertSame('Wrong commodity', $cycle->fresh()->decision_note);
        $this->assertSame(1_000_000_00, $this->cycles->unallocated($inv));

        $this->expectException(BusinessRuleException::class);
        $this->cycles->approve($cycle->fresh(), $this->director2);
    }

    public function test_the_proposer_can_withdraw_a_pending_cycle(): void
    {
        $inv = $this->investment(1_000_000_00);
        $cycle = $this->propose('all');

        $this->cycles->cancel($cycle, $this->sales);

        $this->assertSame(TradeCycle::CANCELLED, $cycle->fresh()->status);
        $this->assertSame(1_000_000_00, $this->cycles->unallocated($inv));
    }

    public function test_after_approval_only_a_director_can_add_capital_but_anyone_can_reduce_it(): void
    {
        $a = $this->investment(1_000_000_00, 'A');
        $b = $this->investment(1_000_000_00, 'B');
        $cycle = $this->propose();
        $this->cycles->allocate($cycle, $a, 400_000_00, $this->sales);
        $this->cycles->approve($cycle, $this->director);

        foreach ([[$a, 600_000_00], [$b, 100_000_00]] as [$inv, $amount]) { // raise A, or add B
            try {
                $this->cycles->allocate($cycle, $inv, $amount, $this->sales);
                $this->fail('sales added capital to an approved cycle');
            } catch (BusinessRuleException $e) {
                $this->assertStringContainsString('Director', $e->getMessage());
            }
        }

        $this->cycles->allocate($cycle, $a, 300_000_00, $this->sales); // reducing is fine
        $this->assertSame(300_000_00, $cycle->fresh()->budget());
        $this->cycles->allocate($cycle, $b, 200_000_00, $this->director); // a Director may add
        $this->assertSame(500_000_00, $cycle->fresh()->budget());
    }

    public function test_the_proposal_is_announced_to_the_other_directors_only(): void
    {
        Notification::fake();
        $this->investment(1_000_000_00);

        $this->propose('all');

        Notification::assertSentTo($this->director, CycleAwaitingApproval::class);
        Notification::assertSentTo($this->director2, CycleAwaitingApproval::class);
        Notification::assertNotSentTo($this->sales, CycleAwaitingApproval::class);

        Notification::fake();
        $this->propose(by: $this->director);
        Notification::assertNotSentTo($this->director, CycleAwaitingApproval::class);
        Notification::assertSentTo($this->director2, CycleAwaitingApproval::class);
    }

    // ---- allocating free capital at creation ----

    public function test_all_free_capital_takes_every_eligible_policys_free_amount(): void
    {
        $a = $this->investment(1_000_000_00, 'A');
        $b = $this->investment(2_000_000_00, 'B');
        $this->cycles->approve($this->cycles->open('Earlier', $this->commodity(), null, $this->sales, 'target', 400_000_00), $this->director); // A and B each give some

        $free = [$a->id => $this->cycles->unallocated($a), $b->id => $this->cycles->unallocated($b)];
        $cycle = $this->propose('all');

        $got = $cycle->allocations()->pluck('amount', 'investment_id')->all();
        $this->assertSame($free, $got);
        $this->assertSame(3_000_000_00 - 400_000_00, $cycle->budget());
        $this->assertSame(0, $this->cycles->unallocated($a));
        $this->assertSame(0, $this->cycles->unallocated($b));
    }

    public function test_nothing_is_created_when_there_is_no_free_capital(): void
    {
        $this->investment(1_000_000_00);
        $this->propose('all');
        $count = TradeCycle::count();

        try {
            $this->propose('all');
            $this->fail('second all-capital cycle created');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('no free capital', $e->getMessage());
        }
        $this->assertSame($count, TradeCycle::count(), 'the half-made cycle was rolled back');
    }

    public function test_a_target_amount_is_shared_pro_rata_and_adds_up_exactly(): void
    {
        $a = $this->investment(1_000_000_00, 'A');
        $b = $this->investment(2_000_000_00, 'B');

        $cycle = $this->propose('target', 1_500_000_00);
        $got = $cycle->allocations()->pluck('amount', 'investment_id')->all();
        $this->assertSame([$a->id => 500_000_00, $b->id => 1_000_000_00], $got);

    }

    public function test_an_awkward_split_still_adds_up_to_the_kobo(): void
    {
        foreach (['X', 'Y', 'Z'] as $name) {
            $this->investment(1_000_000_00, $name);
        }

        $cycle = $this->propose('target', 100); // one naira over three equal policies

        $shares = $cycle->allocations()->orderBy('investment_id')->pluck('amount')->all();
        $this->assertSame(100, array_sum($shares));
        $this->assertSame([34, 33, 33], $shares);
    }

    public function test_a_target_above_the_free_pool_or_a_missing_target_is_refused(): void
    {
        $this->investment(1_000_000_00);

        foreach ([5_000_000_00, null, 0] as $target) {
            try {
                $this->propose('target', $target);
                $this->fail('bad target accepted');
            } catch (BusinessRuleException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, TradeCycle::count());
    }

    public function test_policies_maturing_soon_are_left_out_unless_included(): void
    {
        $far = $this->investment(1_000_000_00, 'Far');
        $soon = $this->investment(1_000_000_00, 'Soon');
        $soon->update(['maturity_date' => now()->addDays(10)]);

        $default = $this->propose('all');
        $this->assertSame([$far->id], $default->allocations()->pluck('investment_id')->all());
        $this->cycles->cancel($default, $this->sales);

        $included = $this->propose('all', include: true);
        $this->assertEqualsCanonicalizing([$far->id, $soon->id], $included->allocations()->pluck('investment_id')->all());
        $this->cycles->cancel($included, $this->sales);

        $shortBuffer = $this->propose('all', buffer: 5); // matures in 10 days, buffer 5: eligible
        $this->assertCount(2, $shortBuffer->allocations);
        $this->cycles->cancel($shortBuffer, $this->sales);

        $plan = $this->cycles->capitalPlan('all');
        $this->assertSame($soon->id, $plan['excluded'][0]['investment']->id);
        $this->assertSame(30, $plan['buffer_days']);
    }

    public function test_only_active_policies_are_eligible(): void
    {
        $active = $this->investment(1_000_000_00, 'Active');
        $matured = $this->investment(1_000_000_00, 'Matured');
        $matured->update(['status' => InvestmentStatus::Matured]);

        $cycle = $this->propose('all');

        $this->assertSame([$active->id], $cycle->allocations()->pluck('investment_id')->all());
    }

    // ---- reservation, limits, warnings ----

    public function test_pending_capital_is_reserved_against_payouts_and_other_cycles(): void
    {
        $inv = $this->investment(1_000_000_00);
        $this->propose('all');
        $inv->update(['status' => InvestmentStatus::Matured]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('still deployed');
        app(CapitalPayoutService::class)->prepare($inv->fresh(), $this->director);
    }

    public function test_the_deployment_limit_counts_capital_waiting_for_approval(): void
    {
        $this->investment(1_000_000_00, 'A');
        $this->investment(1_000_000_00, 'B');
        app(RiskService::class)->save('max_deployment_bps', 6_000, true, $this->director); // 60%

        $first = $this->propose('target', 1_000_000_00); // 50%, still pending
        $this->assertSame(TradeCycle::PENDING, $first->status);

        try {
            $this->propose('target', 500_000_00); // 75% in total
            $this->fail('limit passed by stacking pending cycles');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('limit is 60.0%', $e->getMessage());
        }
        $this->assertSame(1, TradeCycle::count());
    }

    public function test_cash_warning_appears_when_budgets_exceed_main_operations_cash(): void
    {
        $this->assertNull($this->cycles->cashWarning());

        $this->investment(1_000_000_00); // Main Operations 950,000; the other 50,000 is the Risk Fund
        $this->propose('all');            // budget 1,000,000

        $warning = $this->cycles->cashWarning();
        $this->assertNotNull($warning);
        $this->assertStringContainsString('₦1,000,000.00', $warning);
        $this->assertStringContainsString('₦950,000.00', $warning);
    }

    // ---- web ----

    public function test_the_screens_and_permissions(): void
    {
        $this->investment(1_000_000_00, 'Ada');

        $this->actingAs($this->sales)->get(route('admin.trading.cycles'))->assertOk()->assertSee('Propose a cycle')->assertSee('₦1,000,000.00')->assertSee('Include them anyway');

        $this->actingAs($this->sales)->post(route('admin.trading.cycles.store'), ['name' => 'Web', 'commodity_id' => $this->commodity()->id, 'capital' => 'all'])->assertSessionHasNoErrors();
        $cycle = TradeCycle::firstOrFail();
        $this->assertSame(1_000_000_00, $cycle->budget());

        $this->actingAs($this->sales)->get(route('admin.trading.cycles.show', $cycle))->assertOk()->assertSee('Waiting for a Director')->assertDontSee('Approve cycle');
        $this->actingAs($this->director)->get(route('admin.trading.cycles.show', $cycle))->assertSee('Approve cycle');
        $this->actingAs($this->director)->get(route('admin.dashboard'))->assertSee('Trading cycles to approve');

        foreach ([$this->sales, $this->procurement] as $user) {
            $this->actingAs($user)->post(route('admin.trading.cycles.approve', $cycle))->assertForbidden();
            $this->actingAs($user)->post(route('admin.trading.cycles.reject', $cycle), ['note' => 'no'])->assertForbidden();
        }

        $this->actingAs($this->director)->post(route('admin.trading.cycles.reject', $cycle), [])->assertSessionHasErrors('note');
        $this->actingAs($this->director)->post(route('admin.trading.cycles.approve', $cycle))->assertSessionHasNoErrors();
        $this->assertSame(TradeCycle::OPEN, $cycle->fresh()->status);

        $this->actingAs($this->director)->post(route('admin.trading.cycles.approve', $cycle))->assertSessionHasErrors('action'); // double submit
        $this->assertDatabaseHas('activity_log', ['description' => 'Trading cycle approved', 'causer_id' => $this->director->id]);
    }

    public function test_bad_capital_requests_are_shown_as_form_errors(): void
    {
        $this->investment(1_000_000_00);
        $base = ['name' => 'Web', 'commodity_id' => $this->commodity()->id];

        $this->actingAs($this->sales)->post(route('admin.trading.cycles.store'), $base + ['capital' => 'target', 'target_amount' => 'abc'])->assertSessionHasErrors('target_amount');
        $this->actingAs($this->sales)->post(route('admin.trading.cycles.store'), $base + ['capital' => 'target', 'target_amount' => '9,000,000'])->assertSessionHasErrors('capital');
        $this->actingAs($this->sales)->post(route('admin.trading.cycles.store'), $base + ['capital' => 'everything'])->assertSessionHasErrors('capital');
        $this->assertSame(0, TradeCycle::count());
    }
}
