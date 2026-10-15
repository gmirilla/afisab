<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\Investment;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Services\Ledger\LedgerService;
use App\Services\Treasury\TreasuryService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class TreasuryController extends Controller
{
    /** Accounts that are real bank accounts and can be reconciled. */
    private const BANK_ACCOUNTS = [
        LedgerAccount::MAIN_OPS, LedgerAccount::RISK_FUND, LedgerAccount::INVESTOR_PROFIT, LedgerAccount::DOMICILIARY,
    ];

    public function funds(TreasuryService $treasury): View
    {
        return view('admin.funds', [
            'overview' => $treasury->overview(),
            'rows' => $treasury->policyRows(),
            'schedule' => $treasury->maturitySchedule(),
            'accounts' => LedgerAccount::orderBy('id')->get(),
        ]);
    }

    public function account(Request $request, string $code, LedgerService $ledger): View
    {
        $account = LedgerAccount::byCode($code);
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'policy' => ['nullable', 'string', 'max:32'],
        ]);

        $query = JournalLine::with(['entry', 'subject'])
            ->where('ledger_account_id', $account->id)
            ->whereHas('entry', function ($q) use ($filters) {
                if (! empty($filters['from'])) {
                    $q->where('posted_at', '>=', Carbon::parse($filters['from'])->startOfDay());
                }
                if (! empty($filters['to'])) {
                    $q->where('posted_at', '<=', Carbon::parse($filters['to'])->endOfDay());
                }
            });

        $policy = null;
        if (! empty($filters['policy'])) {
            $policy = Investment::where('reference', $filters['policy'])->first();
            $query->where('subject_type', 'investment')->where('subject_id', $policy?->id ?? 0);
        }

        return view('admin.account', [
            'account' => $account,
            'lines' => $query->orderByDesc('id')->paginate(40)->withQueryString(),
            'balance' => $ledger->balance($account),
            'policy' => $policy,
            'filters' => $filters,
        ]);
    }

    public function reconciliations(): View
    {
        return view('admin.reconciliation', [
            'records' => BankReconciliation::with(['account', 'reconciler'])->orderByDesc('as_of')->orderByDesc('id')->limit(100)->get(),
            'accounts' => LedgerAccount::whereIn('code', self::BANK_ACCOUNTS)->get(),
        ]);
    }

    public function reconcile(Request $request, TreasuryService $treasury): RedirectResponse
    {
        $data = $request->validate([
            'account' => ['required', Rule::in(self::BANK_ACCOUNTS)],
            'as_of' => ['required', 'date', 'before_or_equal:today'],
            'statement_balance' => ['required', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $rec = $treasury->reconcile(
                LedgerAccount::byCode($data['account']),
                Carbon::parse($data['as_of']),
                Money::fromNaira($data['statement_balance']),
                $data['note'] ?? null,
                $request->user(),
            );
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['statement_balance' => 'Enter a valid amount in naira.']);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->withErrors(['as_of' => $e->getMessage()]);
        }

        return back()->with('status', $rec->isBalanced()
            ? 'Reconciled: the bank statement agrees with the ledger.'
            : 'Recorded with a difference of '.Money::format(abs($rec->difference)).'. Investigate before relying on this account.');
    }
}
