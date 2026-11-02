<?php

namespace Tests\Feature;

use App\Enums\InvestmentStatus;
use App\Enums\ProfitFrequency;
use App\Models\Commodity;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\LedgerAccount;
use App\Models\TradeCycle;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Performance\PerformanceService;
use App\Services\SubscriptionService;
use App\Services\Trading\CycleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fixes from the site review, phase B: figures that implied money that is not there. */
class ReviewFixesPhaseBTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function policy(bool $pay = true): Investment
    {
        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved', 'nin' => (string) random_int(10_000_000_000, 99_999_999_999)]);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '12-month')->first(), 1_000_000_00, ProfitFrequency::Monthly);
        if ($pay) {
            $payments = app(PaymentService::class);
            $payments->confirm($payments->startTransfer($investment));
        }

        return $investment->fresh();
    }

    public function test_only_money_actually_held_has_a_current_value(): void
    {
        $performance = app(PerformanceService::class);
        $active = $this->policy();
        $unpaid = $this->policy(pay: false);
        $cancelled = $this->policy(pay: false);
        $cancelled->update(['status' => InvestmentStatus::Cancelled]);
        $paidOut = $this->policy();
        $paidOut->update(['status' => InvestmentStatus::PaidOut]);

        $this->assertSame(1_000_000_00, $performance->position($active)['current_value']);
        foreach ([$unpaid, $cancelled->fresh(), $paidOut->fresh()] as $i) {
            $this->assertSame(0, $performance->position($i)['current_value'], $i->status->label());
            $this->assertFalse($i->holdsFunds());
        }
        $this->assertTrue($active->holdsFunds());
    }

    public function test_the_investor_screens_show_a_dash_not_a_value_for_unpaid_and_cancelled_policies(): void
    {
        $investor = $this->policy(pay: false);
        $investor->update(['status' => InvestmentStatus::Cancelled]);

        $dash = $this->actingAs($investor->user)->get(route('investor.dashboard'))->assertOk();
        $dash->assertSee('Cancelled');
        $this->assertMatchesRegularExpression('#Current value</span><br><strong class="text-gray-400">—</strong>#', $dash->getContent());

        $this->actingAs($investor->user)->get(route('investor.investments.show', $investor))->assertOk();
    }

    public function test_an_active_policy_still_shows_its_current_value(): void
    {
        $active = $this->policy();

        $this->actingAs($active->user)->get(route('investor.dashboard'))->assertOk()->assertSee('₦1,000,000.00')->assertDontSee('Not paid yet');
    }

    public function test_a_cancelled_cycle_shows_its_capital_as_released(): void
    {
        $investment = $this->policy();
        $director = User::factory()->create()->assignRole('super-admin');
        $cycles = app(CycleService::class);
        $cycle = $cycles->open('Nov sesame', Commodity::where('name', 'Sesame Seeds')->first(), null, $director, 'none', null, null, false);
        $cycles->allocate($cycle, $investment, 500_000_00, $director);

        $this->actingAs($director)->get(route('admin.trading.cycles.show', $cycle))->assertSee('₦500,000.00');
        $cycles->cancel($cycle->fresh(), $director);

        $this->assertSame(TradeCycle::CANCELLED, $cycle->fresh()->status);
        $page = $this->actingAs($director)->get(route('admin.trading.cycles.show', $cycle))->assertOk();
        $page->assertSee('Released')->assertSee('₦500,000.00 was reserved');
        $this->actingAs($director)->get(route('admin.trading.cycles'))->assertSee('released');
    }

    public function test_the_foreign_currency_account_is_not_presented_as_dollars(): void
    {
        $account = LedgerAccount::byCode(LedgerAccount::DOMICILIARY);

        $this->assertSame('Domiciliary Account (naira value)', $account->name);
        $this->assertStringNotContainsString('USD/GBP/EUR', $account->name);
        $director = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($director)->get(route('admin.dashboard'))->assertSee('Domiciliary Account (naira value)')->assertDontSee('USD/GBP/EUR');
    }
}
