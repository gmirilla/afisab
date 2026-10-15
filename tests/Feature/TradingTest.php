<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Buyer;
use App\Models\Commodity;
use App\Models\CycleExpense;
use App\Models\InventoryLot;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\TradeCycle;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Payments\PaymentService;
use App\Services\Performance\DistributionService;
use App\Services\SubscriptionService;
use App\Services\Trading\CycleService;
use App\Services\Trading\ExpenseService;
use App\Services\Trading\PurchaseService;
use App\Services\Trading\SaleService;
use App\Services\Treasury\CapitalPayoutService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TradingTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    private User $procurement;

    private User $sales;

    private User $accounts;

    private User $admin;

    private Supplier $supplier;

    private Buyer $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-10 10:00'));
        $this->ledger = app(LedgerService::class);

        $this->procurement = User::factory()->create()->assignRole('procurement');
        $this->sales = User::factory()->create()->assignRole('sales');
        $this->accounts = User::factory()->create()->assignRole('accounts');
        $this->admin = User::factory()->create()->assignRole('super-admin');
        $this->supplier = Supplier::create(['name' => 'Benue Aggregators', 'type' => 'aggregator', 'is_active' => true]);
        $this->buyer = Buyer::create(['name' => 'Flour Mills Ltd', 'type' => 'processor', 'is_active' => true]);
    }

    private function investment(int $amount = 1_000_000_00, string $name = 'Ada Obi'): Investment
    {
        $user = User::factory()->create(['name' => $name])->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '12-month')->first(), $amount);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh();
    }

    private function cycle(?Investment $investment = null, int $allocate = 900_000_00): TradeCycle
    {
        $cycle = app(CycleService::class)->open('Sesame Nov', Commodity::where('name', 'Sesame Seeds')->first(), null, $this->sales);
        if ($investment) {
            app(CycleService::class)->allocate($cycle, $investment, $allocate, $this->sales);
        }

        return $cycle;
    }

    /** Propose, approve, pay and receive a purchase of $kg at $pricePerKg (kobo). */
    private function buy(TradeCycle $cycle, int $kg = 10_000, int $pricePerKg = 6_000, bool $qa = true): InventoryLot
    {
        $service = app(PurchaseService::class);
        $purchase = $service->propose($cycle, $this->supplier, $kg, $pricePerKg, $this->procurement);
        $service->approve($purchase, $this->admin);
        $service->pay($purchase, 'NIP-P', $this->accounts);

        return $service->receive($purchase, ['quantity_kg' => $kg, 'warehouse' => 'Makurdi', 'grade' => 'A', 'moisture_percent' => 7.5, 'qa_passed' => $qa], $this->procurement);
    }

    private function sell(TradeCycle $cycle, InventoryLot $lot, int $kg, int $pricePerKg, bool $pay = true): Sale
    {
        $service = app(SaleService::class);
        $sale = $service->create($cycle, $this->buyer, $lot, $kg, $pricePerKg, $this->sales);
        $service->confirm($sale, $this->sales);

        return $pay ? $service->receivePayment($sale, 'BUYER-1', $this->accounts) : $sale->fresh();
    }

    private function bal(string $code): int
    {
        return $this->ledger->balance($code);
    }

    // ---- allocation ----

    public function test_capital_cannot_be_allocated_beyond_the_policy(): void
    {
        $investment = $this->investment(1_000_000_00);

        $this->expectException(BusinessRuleException::class);
        $this->cycle($investment, 1_000_000_01);
    }

    public function test_a_policys_capital_cannot_be_double_allocated_across_open_cycles(): void
    {
        $investment = $this->investment(1_000_000_00);
        $this->cycle($investment, 600_000_00);

        $second = $this->cycle();
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('free to allocate');
        app(CycleService::class)->allocate($second, $investment, 500_000_00, $this->sales); // only 400,000 is free
    }

    public function test_allocation_can_be_updated_and_capital_is_freed_when_a_cycle_closes(): void
    {
        $investment = $this->investment();
        $cycles = app(CycleService::class);
        $cycle = $this->cycle($investment, 500_000_00);

        $cycles->allocate($cycle, $investment, 700_000_00, $this->sales);
        $this->assertSame(700_000_00, $cycle->budget());
        $this->assertSame(300_000_00, $cycles->unallocated($investment));

        $cycle->update(['status' => TradeCycle::CLOSED]);
        $this->assertSame(1_000_000_00, $cycles->unallocated($investment));
    }

    public function test_allocation_cannot_drop_below_what_is_committed(): void
    {
        $investment = $this->investment();
        $cycle = $this->cycle($investment, 900_000_00);
        $this->buy($cycle); // commits 600,000

        $this->expectException(BusinessRuleException::class);
        app(CycleService::class)->allocate($cycle, $investment, 500_000_00, $this->sales);
    }

    // ---- purchases ----

    public function test_a_purchase_cannot_be_approved_by_its_proposer(): void
    {
        $cycle = $this->cycle($this->investment());
        $service = app(PurchaseService::class);
        $purchase = $service->propose($cycle, $this->supplier, 1_000, 6_000, $this->accounts);

        $this->expectException(BusinessRuleException::class);
        $service->approve($purchase, $this->accounts);
    }

    public function test_purchases_cannot_exceed_the_cycle_budget(): void
    {
        $cycle = $this->cycle($this->investment(), 500_000_00);
        $service = app(PurchaseService::class);
        $purchase = $service->propose($cycle, $this->supplier, 10_000, 6_000, $this->procurement); // 600,000 > 500,000

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('exceed the cycle budget');
        $service->approve($purchase, $this->admin);
    }

    public function test_large_purchases_need_a_director(): void
    {
        config(['afisab.purchase_director_threshold' => 500_000_00]);
        $cycle = $this->cycle($this->investment());
        $service = app(PurchaseService::class);
        $purchase = $service->propose($cycle, $this->supplier, 10_000, 6_000, $this->procurement); // 600,000

        try {
            $service->approve($purchase, $this->accounts);
            $this->fail('Accounts approved a purchase above the Director threshold');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Director', $e->getMessage());
        }

        $this->assertSame(Purchase::APPROVED, $service->approve($purchase, $this->admin)->status);
    }

    public function test_paying_a_purchase_books_inventory_and_reduces_cash(): void
    {
        $this->investment(); // Main Ops 950,000
        $cycle = $this->cycle(Investment::first());
        $this->buy($cycle);

        $this->assertSame(600_000_00, $this->bal(LedgerAccount::INVENTORY));
        $this->assertSame(350_000_00, $this->bal(LedgerAccount::MAIN_OPS));
    }

    public function test_the_proposer_cannot_pay_their_own_purchase(): void
    {
        $cycle = $this->cycle($this->investment());
        $service = app(PurchaseService::class);
        $purchase = $service->propose($cycle, $this->supplier, 1_000, 6_000, $this->accounts);
        $service->approve($purchase, $this->admin);

        $this->expectException(BusinessRuleException::class);
        $service->pay($purchase, 'NIP', $this->accounts);
    }

    public function test_cash_guard_blocks_paying_more_than_main_operations_holds(): void
    {
        $cycle = $this->cycle($this->investment(), 900_000_00);
        $service = app(PurchaseService::class);
        $purchase = $service->propose($cycle, $this->supplier, 10_000, 6_000, $this->procurement);
        $service->approve($purchase, $this->admin);

        $this->ledger->transfer(LedgerAccount::MAIN_OPS, LedgerAccount::DOMICILIARY, 800_000_00, 'move cash away'); // leaves 150,000

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Insufficient funds');
        $service->pay($purchase, 'NIP', $this->accounts);
    }

    public function test_receiving_creates_a_lot_and_is_bounded_by_the_order(): void
    {
        $cycle = $this->cycle($this->investment());
        $service = app(PurchaseService::class);
        $purchase = $service->propose($cycle, $this->supplier, 10_000, 6_000, $this->procurement);
        $service->approve($purchase, $this->admin);

        try {
            $service->receive($purchase, ['quantity_kg' => 10_000, 'qa_passed' => true], $this->procurement);
            $this->fail('Received before payment');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }

        $service->pay($purchase, 'NIP', $this->accounts);
        try {
            $service->receive($purchase, ['quantity_kg' => 10_001, 'qa_passed' => true], $this->procurement);
            $this->fail('Received more than ordered');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }

        $lot = $service->receive($purchase, ['quantity_kg' => 9_800, 'moisture_percent' => 8.2, 'qa_passed' => true, 'warehouse' => 'Makurdi'], $this->procurement);
        $this->assertSame(9_800, $lot->remaining_kg);
        $this->assertSame(600_000_00, $lot->remaining_cost); // all that was paid is the cost of what arrived
        $this->assertSame(Purchase::RECEIVED, $purchase->fresh()->status);
    }

    // ---- stock and sales ----

    public function test_stock_that_failed_qa_cannot_be_sold(): void
    {
        $cycle = $this->cycle($this->investment());
        $lot = $this->buy($cycle, qa: false);

        $this->expectException(BusinessRuleException::class);
        app(SaleService::class)->create($cycle, $this->buyer, $lot, 100, 7_500, $this->sales);
    }

    public function test_stock_cannot_be_oversold_even_with_concurrent_drafts(): void
    {
        $cycle = $this->cycle($this->investment());
        $lot = $this->buy($cycle);
        $service = app(SaleService::class);

        $a = $service->create($cycle, $this->buyer, $lot, 7_000, 7_500, $this->sales);
        $b = $service->create($cycle, $this->buyer, $lot, 7_000, 7_500, $this->sales); // both drafts are allowed
        $service->confirm($a, $this->sales);

        $this->expectException(BusinessRuleException::class); // but only one can reserve the stock
        $service->confirm($b, $this->sales);
    }

    public function test_cost_of_goods_is_consumed_exactly_across_partial_sales(): void
    {
        $cycle = $this->cycle($this->investment());
        $lot = $this->buy($cycle); // 10,000 kg costing 60,000,000 kobo

        $sales = collect([3_333, 3_333, 3_334])->map(fn ($kg) => $this->sell($cycle, $lot, $kg, 7_500, pay: false));

        $this->assertSame(60_000_000, $sales->sum('cost_of_goods'));
        $this->assertSame(0, $lot->fresh()->remaining_cost);
        $this->assertSame(0, $lot->fresh()->remaining_kg);
    }

    public function test_cancelling_a_confirmed_sale_returns_the_stock(): void
    {
        $cycle = $this->cycle($this->investment());
        $lot = $this->buy($cycle);
        $sale = $this->sell($cycle, $lot, 4_000, 7_500, pay: false);
        $this->assertSame(6_000, $lot->fresh()->remaining_kg);

        app(SaleService::class)->cancel($sale, $this->sales);

        $this->assertSame(10_000, $lot->fresh()->remaining_kg);
        $this->assertSame(60_000_000, $lot->fresh()->remaining_cost);
    }

    public function test_buyer_payment_books_revenue_and_cost(): void
    {
        $cycle = $this->cycle($this->investment());
        $lot = $this->buy($cycle);

        $this->sell($cycle, $lot, 10_000, 7_500);

        $this->assertSame(75_000_000, $this->bal(LedgerAccount::INVESTOR_PROFIT));  // buyer pays into the Profit Account
        $this->assertSame(0, $this->bal(LedgerAccount::INVENTORY));                 // cost of goods released
        $this->assertSame(15_000_000, $this->bal(LedgerAccount::TRADING_INCOME));   // margin
    }

    public function test_selling_below_cost_books_a_loss(): void
    {
        $cycle = $this->cycle($this->investment());
        $lot = $this->buy($cycle);

        $this->sell($cycle, $lot, 10_000, 5_000); // 500,000 for stock that cost 600,000

        $this->assertSame(50_000_000, $this->bal(LedgerAccount::INVESTOR_PROFIT));
        $this->assertSame(0, $this->bal(LedgerAccount::INVENTORY));
        $this->assertSame(-10_000_000, $this->bal(LedgerAccount::TRADING_INCOME)); // debit balance on an income account = loss
    }

    // ---- expenses ----

    public function test_expenses_are_proposed_by_one_person_and_paid_by_another(): void
    {
        $cycle = $this->cycle($this->investment());
        $service = app(ExpenseService::class);
        $expense = $service->propose($cycle, 'transport', 'Truck Makurdi to Lagos', 20_000_00, $this->sales);

        try {
            $service->pay($expense, 'NIP', $this->sales);
            $this->fail('Proposer paid own expense');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }

        $service->pay($expense, 'NIP-E', $this->accounts);
        $this->assertSame(CycleExpense::PAID, $expense->fresh()->status);
        $this->assertSame(20_000_00, $this->bal(LedgerAccount::TRADING_EXPENSES));
    }

    // ---- closing a cycle ----

    public function test_a_cycle_cannot_close_with_loose_ends(): void
    {
        $cycle = $this->cycle($this->investment());
        $lot = $this->buy($cycle);
        $this->sell($cycle, $lot, 4_000, 7_500); // 6,000 kg still unsold

        try {
            app(CycleService::class)->close($cycle, $this->sales);
            $this->fail('Closed with stock remaining');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('unsold stock', $e->getMessage());
        }
        $this->assertSame(TradeCycle::OPEN, $cycle->fresh()->status);
    }

    public function test_closing_settles_the_cash_and_records_the_result(): void
    {
        $investment = $this->investment();
        $cycle = $this->cycle($investment, 900_000_00);
        $lot = $this->buy($cycle);                                                      // cost 600,000
        $expense = app(ExpenseService::class)->propose($cycle, 'transport', 'Haulage', 20_000_00, $this->sales);
        app(ExpenseService::class)->pay($expense, 'NIP-E', $this->accounts);              // expenses 20,000
        $this->sell($cycle, $lot, 10_000, 7_500);                                       // sales 750,000

        $closed = app(CycleService::class)->close($cycle, $this->sales);

        // net = 750,000 - 600,000 - 20,000 = 130,000; 20% (26,000) stays in the Investors Profit Account
        $this->assertSame(TradeCycle::CLOSED, $closed->status);
        $this->assertSame(13_000_000, $closed->net_profit);
        $this->assertSame(2_600_000, $closed->retained);
        $this->assertSame(2_600_000, $this->bal(LedgerAccount::INVESTOR_PROFIT));
        // Main Ops: 950,000 - 600,000 - 20,000 + (750,000 - 26,000) = 1,054,000
        $this->assertSame(105_400_000, $this->bal(LedgerAccount::MAIN_OPS));
        $this->assertSame(0, $this->bal(LedgerAccount::INVENTORY));

        // The result is recorded against the allocated policy, ready for the monthly distribution.
        $entry = $investment->performanceEntries()->first();
        $this->assertSame($cycle->reference, $entry->cycle_reference);
        $this->assertSame(13_000_000, $entry->net_profit);
    }

    public function test_a_closed_cycle_flows_through_the_monthly_distribution(): void
    {
        $investment = $this->investment();
        $cycle = $this->cycle($investment, 900_000_00);
        $lot = $this->buy($cycle);
        $expense = app(ExpenseService::class)->propose($cycle, 'transport', 'Haulage', 20_000_00, $this->sales);
        app(ExpenseService::class)->pay($expense, 'NIP-E', $this->accounts);
        $this->sell($cycle, $lot, 10_000, 7_500);
        app(CycleService::class)->close($cycle, $this->sales);

        $dist = app(DistributionService::class);
        $run = $dist->calculate(Carbon::parse('2026-10-01'), notify: false);
        $line = $run->lines()->first();

        // 130,000 net: 20% retained, then 30% / 70% of the remaining 104,000
        $this->assertSame(13_000_000, $line->net_profit);
        $this->assertSame(3_120_000, $line->investor_share);
        $this->assertSame(7_280_000, $line->company_share);
    }

    public function test_the_whole_ledger_still_balances_after_a_full_cycle(): void
    {
        $investment = $this->investment();
        $cycle = $this->cycle($investment);
        $lot = $this->buy($cycle);
        $this->sell($cycle, $lot, 10_000, 7_500);
        app(CycleService::class)->close($cycle, $this->sales);

        $debits = JournalLine::where('direction', 'debit')->sum('amount');
        $credits = JournalLine::where('direction', 'credit')->sum('amount');
        $this->assertSame($debits, $credits);
    }

    public function test_a_losing_cycle_retains_nothing_and_distributes_nothing(): void
    {
        $investment = $this->investment();
        $cycle = $this->cycle($investment);
        $lot = $this->buy($cycle);
        $this->sell($cycle, $lot, 10_000, 5_000); // loss of 100,000

        $closed = app(CycleService::class)->close($cycle, $this->sales);

        $this->assertSame(-10_000_000, $closed->net_profit);
        $this->assertSame(0, $closed->retained);
        $this->assertSame(0, $this->bal(LedgerAccount::INVESTOR_PROFIT)); // everything returned to Main Ops
        $this->assertSame(95_000_000 - 60_000_000 + 50_000_000, $this->bal(LedgerAccount::MAIN_OPS));

        $run = app(DistributionService::class)->calculate(Carbon::parse('2026-10-01'), notify: false);
        $this->assertSame(0, $run->lines()->first()->investor_share);
    }

    public function test_a_cycle_with_no_money_moved_can_be_cancelled_but_not_after(): void
    {
        $cycle = $this->cycle($this->investment());
        $service = app(PurchaseService::class);
        $pending = $service->propose($cycle, $this->supplier, 100, 6_000, $this->procurement);

        $this->assertSame(TradeCycle::CANCELLED, app(CycleService::class)->cancel($cycle, $this->sales)->status);
        $this->assertSame(Purchase::CANCELLED, $pending->fresh()->status);

        $second = $this->cycle(Investment::first(), 100_000_00);
        $this->buy($second, 1_000, 6_000);
        $this->expectException(BusinessRuleException::class);
        app(CycleService::class)->cancel($second, $this->sales);
    }

    public function test_capital_deployed_in_an_open_cycle_blocks_its_payout(): void
    {
        $investment = $this->investment();
        $this->cycle($investment);
        $investment->update(['status' => 'matured']);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('still deployed');
        app(CapitalPayoutService::class)->prepare($investment, $this->accounts);
    }

    // ---- screens and access ----

    public function test_roles_see_and_do_only_their_part_of_trading(): void
    {
        $investment = $this->investment();
        $cycle = $this->cycle($investment);
        $lot = $this->buy($cycle);

        // Everyone with trading.view can look
        foreach ([$this->sales, $this->procurement, $this->accounts] as $user) {
            $this->actingAs($user)->get(route('admin.trading.cycles'))->assertOk();
            $this->actingAs($user)->get(route('admin.trading.cycles.show', $cycle))->assertOk()->assertSee($cycle->reference);
        }
        $this->actingAs($this->accounts)->get(route('admin.trading.inventory'))->assertOk()->assertSee('Makurdi');
        $this->actingAs(User::factory()->create()->assignRole('auditor'))->get(route('admin.trading.cycles'))->assertOk();

        // Investors and HR cannot
        $this->actingAs($investment->user)->get(route('admin.trading.cycles'))->assertForbidden();
        $this->actingAs(User::factory()->create()->assignRole('hr'))->get(route('admin.trading.cycles'))->assertForbidden();

        // Separation of duties
        $this->actingAs($this->procurement)->post(route('admin.trading.cycles.store'), ['name' => 'x', 'commodity_id' => 1])->assertForbidden();
        $sale = $this->sell($cycle, $lot, 1_000, 7_500, pay: false);
        $this->actingAs($this->sales)->post(route('admin.trading.sales.receive', $sale), ['reference' => 'X'])->assertForbidden();
        $this->actingAs($this->accounts)->post(route('admin.trading.sales.receive', $sale), ['reference' => 'BUYER-2'])->assertSessionHasNoErrors();
    }

    public function test_the_full_cycle_can_be_driven_through_the_web_screens(): void
    {
        $investment = $this->investment();
        $commodity = Commodity::where('name', 'Sesame Seeds')->first();

        $this->actingAs($this->sales)->post(route('admin.trading.cycles.store'), ['name' => 'Web cycle', 'commodity_id' => $commodity->id])->assertRedirect();
        $cycle = TradeCycle::firstOrFail();

        $this->actingAs($this->sales)->post(route('admin.trading.cycles.allocate', $cycle), ['investment_id' => $investment->id, 'amount' => '900000'])->assertSessionHasNoErrors();

        $this->actingAs($this->procurement)->post(route('admin.trading.purchases.propose', $cycle), ['supplier_id' => $this->supplier->id, 'quantity_kg' => 10000, 'unit_price' => '60'])->assertSessionHasNoErrors();
        $purchase = Purchase::firstOrFail();
        $this->assertSame(60_000_000, $purchase->total);

        $this->actingAs($this->procurement)->post(route('admin.trading.purchases.approve', $purchase))->assertForbidden();   // procurement cannot approve
        $this->actingAs($this->admin)->post(route('admin.trading.purchases.approve', $purchase))->assertSessionHasNoErrors();
        $this->actingAs($this->sales)->post(route('admin.trading.purchases.pay', $purchase), ['reference' => 'X'])->assertForbidden(); // sales cannot pay
        $this->actingAs($this->accounts)->post(route('admin.trading.purchases.pay', $purchase), ['reference' => 'NIP-1'])->assertSessionHasNoErrors();
        $this->actingAs($this->procurement)->post(route('admin.trading.purchases.receive', $purchase), ['quantity_kg' => 10000, 'qa_passed' => 1, 'warehouse' => 'Makurdi', 'moisture_percent' => '7.5'])->assertSessionHasNoErrors();
        $lot = InventoryLot::firstOrFail();

        $this->actingAs($this->sales)->post(route('admin.trading.sales.store', $cycle), ['buyer_id' => $this->buyer->id, 'inventory_lot_id' => $lot->id, 'quantity_kg' => 10000, 'unit_price' => '75'])->assertSessionHasNoErrors();
        $sale = Sale::firstOrFail();
        $this->actingAs($this->sales)->post(route('admin.trading.sales.confirm', $sale))->assertSessionHasNoErrors();
        $this->actingAs($this->sales)->post(route('admin.trading.sales.receive', $sale), ['reference' => 'B'])->assertForbidden();   // sales cannot mark paid
        $this->actingAs($this->accounts)->post(route('admin.trading.sales.receive', $sale), ['reference' => 'BUYER-9'])->assertSessionHasNoErrors();

        $this->actingAs($this->accounts)->post(route('admin.trading.cycles.close', $cycle))->assertForbidden();          // accounts cannot close
        $this->actingAs($this->sales)->post(route('admin.trading.cycles.close', $cycle))->assertSessionHasNoErrors();

        $this->assertSame(TradeCycle::CLOSED, $cycle->fresh()->status);
        $this->assertSame(15_000_000, $cycle->fresh()->net_profit);
    }

    public function test_business_rule_failures_show_as_form_errors_not_server_errors(): void
    {
        $cycle = $this->cycle($this->investment(), 100_000_00);

        $this->actingAs($this->procurement)
            ->post(route('admin.trading.purchases.propose', $cycle), ['supplier_id' => $this->supplier->id, 'quantity_kg' => 1000, 'unit_price' => 'abc'])
            ->assertSessionHasErrors('action');
    }

    public function test_suppliers_and_buyers_are_managed_by_the_right_roles(): void
    {
        $this->actingAs($this->procurement)->post(route('admin.trading.suppliers.save'), ['name' => 'New Farm', 'type' => 'farmer', 'account_number' => '0123456789'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('suppliers', ['name' => 'New Farm']);
        $this->actingAs($this->sales)->post(route('admin.trading.suppliers.save'), ['name' => 'Nope', 'type' => 'farmer'])->assertForbidden();

        $this->actingAs($this->sales)->post(route('admin.trading.buyers.save'), ['name' => 'Export Co', 'type' => 'export'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('buyers', ['name' => 'Export Co']);
        $this->actingAs($this->procurement)->post(route('admin.trading.buyers.save'), ['name' => 'Nope', 'type' => 'local'])->assertForbidden();
    }
}
