<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\LedgerReversal;
use App\Services\Ledger\ReversalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReversalController extends Controller
{
    public function index(): View
    {
        return view('admin.reversals', [
            'reversals' => LedgerReversal::with(['entry', 'reversalEntry', 'requester', 'decider'])->orderByDesc('id')->limit(100)->get()
                ->sortBy(fn (LedgerReversal $r) => $r->status === LedgerReversal::REQUESTED ? 0 : 1)->values(), // pending first, portable (no raw SQL)
        ]);
    }

    public function entry(JournalEntry $entry, ReversalService $reversals): View
    {
        $entry->load(['lines.account', 'lines.subject', 'poster']);

        return view('admin.entry', [
            'entry' => $entry,
            'isReversal' => $reversals->isReversal($entry),
            'reversal' => LedgerReversal::with(['requester', 'decider', 'reversalEntry'])
                ->where('journal_entry_id', $entry->id)->whereIn('status', [LedgerReversal::REQUESTED, LedgerReversal::APPROVED])->first(),
            'reverses' => $reversals->isReversal($entry)
                ? JournalEntry::where('reference', substr($entry->reference, strlen(ReversalService::PREFIX)))->first()
                : null,
        ]);
    }

    public function request(Request $request, JournalEntry $entry, ReversalService $reversals): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:200']]);

        return $this->attempt(fn () => $reversals->request($entry, $data['reason'], $request->user()), 'Reversal requested. A Director must approve it before anything is posted.', route('admin.reversals'));
    }

    public function approve(Request $request, LedgerReversal $reversal, ReversalService $reversals): RedirectResponse
    {
        return $this->attempt(fn () => $reversals->approve($reversal, $request->user()), 'Reversal approved and posted to the ledger.');
    }

    public function reject(Request $request, LedgerReversal $reversal, ReversalService $reversals): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:200']]);

        return $this->attempt(fn () => $reversals->reject($reversal, $request->user(), $data['note']), 'Reversal rejected. The ledger is unchanged.');
    }

    private function attempt(callable $action, string $success, ?string $to = null): RedirectResponse
    {
        try {
            $action();
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['reversal' => $e->getMessage()]);
        }

        return ($to ? redirect($to) : back())->with('status', $success);
    }
}
