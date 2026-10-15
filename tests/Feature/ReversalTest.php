<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\LedgerReversal;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Ledger\ReversalService;
use App\Services\Payments\PaymentService;
use App\Services\SubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReversalTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    private ReversalService $reversals;

    private User $accounts;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->ledger = app(LedgerService::class);
        $this->reversals = app(ReversalService::class);
        $this->accounts = User::factory()->create()->assignRole('accounts');
        $this->director = User::factory()->create()->assignRole('super-admin');
    }

    private function investment(int $amount = 1_000_000_00): Investment
    {
        $user = User::factory()->create()->assignRole('investor');
        $user->investorProfile()->create(['phone' => '0800', 'kyc_status' => 'approved']);
        $investment = app(SubscriptionService::class)->subscribe($user, InvestmentPlan::where('slug', '3-month')->first(), $amount);
        $payments = app(PaymentService::class);
        $payments->confirm($payments->startTransfer($investment));

        return $investment->fresh();
    }

    /** A plain cash-in entry that is safe to reverse. */
    private function receipt(int $amount = 100_000_00): JournalEntry
    {
        return $this->ledger->transfer(LedgerAccount::INVENTORY, LedgerAccount::MAIN_OPS, $amount, 'Simulated receipt');
    }

    public function test_approved_reversal_posts_an_equal_and_opposite_entry_and_leaves_the_original_untouched(): void
    {
        $entry = $this->receipt(100_000_00);
        $before = $this->ledger->balance(LedgerAccount::MAIN_OPS);

        $request = $this->reversals->request($entry, 'Posted to the wrong account', $this->accounts);
        $this->assertSame($before, $this->ledger->balance(LedgerAccount::MAIN_OPS), 'a request alone moves nothing');

        $done = $this->reversals->approve($request, $this->director);

        $this->assertSame(LedgerReversal::APPROVED, $done->status);
        $this->assertSame($before - 100_000_00, $this->ledger->balance(LedgerAccount::MAIN_OPS));
        $mirror = $done->reversalEntry->load('lines.account');
        $this->assertSame('REV-'.$entry->reference, $mirror->reference);
        $this->assertSame(2, $mirror->lines->count());
        foreach ($entry->lines as $orig) {
            $flipped = $mirror->lines->firstWhere('ledger_account_id', $orig->ledger_account_id);
            $this->assertSame($orig->direction === JournalLine::DEBIT ? JournalLine::CREDIT : JournalLine::DEBIT, $flipped->direction);
            $this->assertSame($orig->amount, $flipped->amount);
        }
        $this->assertSame(2, $entry->fresh()->lines()->count(), 'original entry is unchanged');
    }

    public function test_reversal_keeps_the_policy_subject_so_per_policy_balances_move_back(): void
    {
        $investment = $this->investment();
        // Confirming a payment posts the receipt and then the 5% reserve transfer; undo them newest first.
        $entries = JournalEntry::whereHas('lines', fn ($q) => $q->where('subject_type', 'investment')->where('subject_id', $investment->id))->orderByDesc('id')->get();
        $this->assertGreaterThan(0, $this->ledger->balance(LedgerAccount::INVESTOR_FUNDS, $investment));

        foreach ($entries as $entry) {
            $this->reversals->approve($this->reversals->request($entry, 'Duplicate confirmation', $this->accounts), $this->director);
        }

        $this->assertSame(0, $this->ledger->balance(LedgerAccount::INVESTOR_FUNDS, $investment));
        $this->assertSame(0, $this->ledger->balance(LedgerAccount::MAIN_OPS, $investment));
        $this->assertSame(JournalLine::where('direction', JournalLine::DEBIT)->sum('amount'), JournalLine::where('direction', JournalLine::CREDIT)->sum('amount'), 'ledger still balanced');
    }

    public function test_a_different_person_must_approve(): void
    {
        $request = $this->reversals->request($this->receipt(), 'Wrong amount', $this->accounts);

        try {
            $this->reversals->approve($request, $this->accounts);
            $this->fail('self-approval allowed');
        } catch (BusinessRuleException) {
        }

        $this->assertSame(LedgerReversal::REQUESTED, $request->fresh()->status);
        $this->assertSame(0, JournalEntry::where('reference', 'like', 'REV-%')->count(), 'nothing posted');
        $this->assertSame(LedgerReversal::APPROVED, $this->reversals->approve($request, $this->director)->status);
    }

    public function test_an_entry_can_only_be_reversed_once_and_a_reversal_cannot_be_reversed(): void
    {
        $entry = $this->receipt();
        $first = $this->reversals->request($entry, 'Mistake', $this->accounts);

        try {
            $this->reversals->request($entry, 'Again', $this->accounts);
            $this->fail('duplicate pending request allowed');
        } catch (BusinessRuleException) {
        }

        $this->reversals->approve($first, $this->director);

        try {
            $this->reversals->request($entry->fresh(), 'Again', $this->accounts);
            $this->fail('second reversal after approval allowed');
        } catch (BusinessRuleException) {
        }

        $this->expectException(BusinessRuleException::class);
        $this->reversals->request($first->fresh()->reversalEntry, 'Undo the undo', $this->accounts);
    }

    public function test_approved_request_cannot_be_approved_twice(): void
    {
        $request = $this->reversals->request($this->receipt(), 'Mistake', $this->accounts);
        $this->reversals->approve($request, $this->director);

        $this->expectException(BusinessRuleException::class);
        $this->reversals->approve($request, $this->director);
    }

    public function test_rejection_changes_nothing_and_frees_the_entry_for_a_new_request(): void
    {
        $entry = $this->receipt();
        $before = $this->ledger->balance(LedgerAccount::MAIN_OPS);

        $this->reversals->reject($this->reversals->request($entry, 'Mistake', $this->accounts), $this->director, 'Not a mistake');

        $this->assertSame($before, $this->ledger->balance(LedgerAccount::MAIN_OPS));
        $this->assertSame(LedgerReversal::REQUESTED, $this->reversals->request($entry, 'Better reason', $this->accounts)->status);
    }

    public function test_a_reversal_that_would_overdraw_a_bank_account_is_refused_and_rolled_back(): void
    {
        $receipt = $this->receipt(100_000_00);
        $this->ledger->transfer(LedgerAccount::MAIN_OPS, LedgerAccount::INVENTORY, 100_000_00, 'Money spent'); // cash now zero
        $request = $this->reversals->request($receipt, 'Wrong receipt', $this->accounts);

        try {
            $this->reversals->approve($request, $this->director);
            $this->fail('overdrawing reversal allowed');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('below zero', $e->getMessage());
        }

        $this->assertSame(0, $this->ledger->balance(LedgerAccount::MAIN_OPS));
        $this->assertNull(JournalEntry::where('reference', 'REV-'.$receipt->reference)->first(), 'mirror entry rolled back');
        $this->assertSame(LedgerReversal::REQUESTED, $request->fresh()->status);
    }

    public function test_reason_is_required(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->reversals->request($this->receipt(), '   ', $this->accounts);
    }

    public function test_screens_and_permissions(): void
    {
        $entry = $this->receipt();
        $auditor = User::factory()->create()->assignRole('auditor');
        $procurement = User::factory()->create()->assignRole('procurement');

        // Accounts requests through the screen.
        $this->actingAs($this->accounts)->get(route('admin.ledger.entry', $entry))->assertOk()->assertSee('Request reversal');
        $this->actingAs($this->accounts)->post(route('admin.reversals.request', $entry), ['reason' => 'x'])->assertSessionHasErrors('reason');
        $this->actingAs($this->accounts)->post(route('admin.reversals.request', $entry), ['reason' => 'Entered twice'])->assertRedirect(route('admin.reversals'));
        $reversal = LedgerReversal::firstOrFail();

        // Accounts cannot approve; auditors can look but not touch; others see nothing.
        $this->actingAs($this->accounts)->post(route('admin.reversals.approve', $reversal))->assertForbidden();
        $this->actingAs($auditor)->get(route('admin.reversals'))->assertOk()->assertSee('Entered twice');
        $this->actingAs($auditor)->get(route('admin.ledger.entry', $entry))->assertOk()->assertDontSee('Request reversal');
        $this->actingAs($auditor)->post(route('admin.reversals.request', $entry), ['reason' => 'Trying it'])->assertForbidden();
        $this->actingAs($auditor)->post(route('admin.reversals.approve', $reversal))->assertForbidden();
        $this->actingAs($procurement)->get(route('admin.reversals'))->assertForbidden();
        $this->actingAs($procurement)->get(route('admin.ledger.entry', $entry))->assertForbidden();

        // The Director sees it in the work queue and approves.
        $this->actingAs($this->director)->get(route('admin.dashboard'))->assertSee('Ledger reversals to approve');
        $this->actingAs($this->director)->post(route('admin.reversals.approve', $reversal))->assertSessionHasNoErrors();
        $this->assertSame(LedgerReversal::APPROVED, $reversal->fresh()->status);

        // Double-submit is harmless: the second approval is refused and nothing more is posted.
        $count = JournalEntry::count();
        $this->actingAs($this->director)->post(route('admin.reversals.approve', $reversal))->assertSessionHasErrors('reversal');
        $this->assertSame($count, JournalEntry::count());

        $this->actingAs($this->accounts)->get(route('admin.ledger.entry', $reversal->fresh()->reversalEntry))->assertOk()->assertSee('cannot be reversed again');
    }

    public function test_requesting_and_approving_are_in_the_audit_trail(): void
    {
        $request = $this->reversals->request($this->receipt(), 'Mistake', $this->accounts);
        $this->reversals->approve($request, $this->director);

        $this->assertDatabaseHas('activity_log', ['description' => 'Ledger reversal requested', 'causer_id' => $this->accounts->id]);
        $this->assertDatabaseHas('activity_log', ['description' => 'Ledger reversal approved and posted', 'causer_id' => $this->director->id]);
    }
}
