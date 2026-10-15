<?php

namespace Tests\Feature;

use App\Exceptions\LedgerException;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Ledger\InvestorFundsService;
use App\Services\Ledger\LedgerService;
use Database\Seeders\LedgerAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountSeeder::class);
        $this->ledger = app(LedgerService::class);
    }

    public function test_unbalanced_entry_is_rejected_and_nothing_is_saved(): void
    {
        $this->expectException(LedgerException::class);

        try {
            $this->ledger->post('bad', [
                ['account' => LedgerAccount::MAIN_OPS, 'direction' => 'debit', 'amount' => 100],
                ['account' => LedgerAccount::INVESTOR_FUNDS, 'direction' => 'credit', 'amount' => 90],
            ]);
        } finally {
            $this->assertSame(0, JournalEntry::count());
        }
    }

    public function test_unknown_account_rolls_back_the_whole_entry(): void
    {
        try {
            $this->ledger->post('bad', [
                ['account' => LedgerAccount::MAIN_OPS, 'direction' => 'debit', 'amount' => 100],
                ['account' => 'NOPE', 'direction' => 'credit', 'amount' => 100],
            ]);
            $this->fail('Expected LedgerException');
        } catch (LedgerException) {
            $this->assertSame(0, JournalEntry::count());
            $this->assertSame(0, JournalLine::count());
        }
    }

    public function test_entries_and_lines_are_immutable(): void
    {
        $entry = $this->ledger->transfer(LedgerAccount::INVESTOR_FUNDS, LedgerAccount::MAIN_OPS, 100, 't');

        $this->expectException(LedgerException::class);
        $entry->lines->first()->update(['amount' => 1]);
    }

    public function test_investor_funds_apply_five_percent_risk_reserve(): void
    {
        $policy = User::factory()->create(); // stands in for an investment policy
        app(InvestorFundsService::class)->receive(1_000_000_00, $policy, 'INV-1');

        $this->assertSame(950_000_00, $this->ledger->balance(LedgerAccount::MAIN_OPS));
        $this->assertSame(50_000_00, $this->ledger->balance(LedgerAccount::RISK_FUND));
        $this->assertSame(1_000_000_00, $this->ledger->balance(LedgerAccount::INVESTOR_FUNDS));
        $this->assertSame(950_000_00, $this->ledger->balance(LedgerAccount::MAIN_OPS, $policy));
    }

    public function test_balances_can_be_read_per_policy(): void
    {
        [$a, $b] = User::factory()->count(2)->create();
        $svc = app(InvestorFundsService::class);
        $svc->receive(500_000_00, $a, 'A');
        $svc->receive(2_000_000_00, $b, 'B');

        $this->assertSame(475_000_00, $this->ledger->balance(LedgerAccount::MAIN_OPS, $a));
        $this->assertSame(1_900_000_00, $this->ledger->balance(LedgerAccount::MAIN_OPS, $b));
        $this->assertSame(2_375_000_00, $this->ledger->balance(LedgerAccount::MAIN_OPS));
    }

    public function test_cycle_settlement_retains_twenty_percent_of_net_profit(): void
    {
        $policy = User::factory()->create();
        $svc = app(InvestorFundsService::class);

        // Sales 1,300,000 on capital 1,000,000 with 100,000 expenses => net profit 200,000, retain 40,000.
        $result = $svc->settleCycle(1_300_000_00, 1_000_000_00, 100_000_00, $policy, 'CYC-1');

        $this->assertSame(200_000_00, $result['net_profit']);
        $this->assertSame(40_000_00, $result['retained']);
        $this->assertSame(160_000_00, $result['distributable']);
        $this->assertSame(40_000_00, $this->ledger->balance(LedgerAccount::INVESTOR_PROFIT));
        $this->assertSame(1_260_000_00, $this->ledger->balance(LedgerAccount::MAIN_OPS));
    }

    public function test_loss_cycle_retains_nothing(): void
    {
        $policy = User::factory()->create();
        $result = app(InvestorFundsService::class)->settleCycle(900_000_00, 1_000_000_00, 0, $policy, 'CYC-2');

        $this->assertSame(0, $result['retained']);
        $this->assertSame(0, $this->ledger->balance(LedgerAccount::INVESTOR_PROFIT));
    }

    public function test_every_entry_balances(): void
    {
        $policy = User::factory()->create();
        app(InvestorFundsService::class)->receive(777_777_77, $policy, 'ODD');

        foreach (JournalEntry::with('lines')->get() as $entry) {
            $this->assertSame(
                $entry->lines->where('direction', 'debit')->sum('amount'),
                $entry->lines->where('direction', 'credit')->sum('amount'),
            );
        }
    }
}
