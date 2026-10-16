<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvestmentStatus;
use App\Enums\KycStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CapitalPayout;
use App\Models\CycleExpense;
use App\Models\LedgerReversal;
use App\Models\StatutoryRemittance;
use App\Models\DistributionLine;
use App\Models\DistributionRun;
use App\Models\Investment;
use App\Models\InvestorProfile;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\Ledger\LedgerService;
use App\Support\SchedulerHealth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(LedgerService $ledger): View
    {
        $user = auth()->user();
        $canSeeFunds = $user->can('funds.view');

        return view('admin.dashboard', [
            'balances' => $canSeeFunds ? collect([
                LedgerAccount::MAIN_OPS, LedgerAccount::RISK_FUND,
                LedgerAccount::INVESTOR_PROFIT, LedgerAccount::DOMICILIARY,
            ])->mapWithKeys(fn ($code) => [LedgerAccount::byCode($code)->name => $ledger->balance($code)]) : null,
            'investorFunds' => $canSeeFunds ? $ledger->balance(LedgerAccount::INVESTOR_FUNDS) : null,
            'activeInvestments' => Investment::where('status', InvestmentStatus::Active)->count(),
            'queue' => $this->queue($user),
            'schedulerWarning' => $user->canAny(['payouts.prepare', 'payouts.approve', 'payroll.pay']) ? SchedulerHealth::warning() : null,
        ]);
    }

    /**
     * "What is waiting for me": only items this person has the permission to act on.
     *
     * @return array<int, array{label: string, count: int, route: string}>
     */
    private function queue($user): array
    {
        $items = [
            ['payments.confirm', 'Bank-transfer payments to confirm', Payment::where('status', PaymentStatus::AwaitingConfirmation)->count(), 'admin.payments'],
            ['kyc.review', 'KYC submissions to review', InvestorProfile::where('kyc_status', KycStatus::Submitted)->count(), 'admin.kyc'],
            ['purchases.approve', 'Purchases to approve', Purchase::where('status', Purchase::PROPOSED)->count(), 'admin.trading.cycles'],
            ['purchases.pay', 'Approved purchases to pay', Purchase::where('status', Purchase::APPROVED)->count(), 'admin.trading.cycles'],
            ['purchases.pay', 'Expenses to pay', CycleExpense::where('status', CycleExpense::PROPOSED)->count(), 'admin.trading.cycles'],
            ['inventory.manage', 'Paid purchases awaiting goods', Purchase::where('status', Purchase::PAID)->count(), 'admin.trading.cycles'],
            ['sales.receive', 'Sales awaiting buyer payment', Sale::where('status', Sale::CONFIRMED)->count(), 'admin.trading.cycles'],
            ['payouts.approve', 'Profit distribution runs to approve', DistributionRun::where('status', DistributionRun::CALCULATED)->count(), 'admin.distributions'],
            ['payouts.prepare', 'Investor profit payments to make', DistributionLine::where('status', DistributionLine::PAYABLE)->count(), 'admin.distributions'],
            ['payouts.approve', 'Capital payouts to approve', CapitalPayout::where('status', CapitalPayout::PREPARED)->count(), 'admin.payouts'],
            ['payouts.prepare', 'Capital payouts to pay', CapitalPayout::where('status', CapitalPayout::APPROVED)->count(), 'admin.payouts'],
            ['reversals.approve', 'Ledger reversals to approve', LedgerReversal::where('status', LedgerReversal::REQUESTED)->count(), 'admin.reversals'],
            ['payroll.pay', 'PAYE, pension and NHF remittances to pay', StatutoryRemittance::where('status', StatutoryRemittance::DUE)->count(), 'admin.hr.remittances'],
            ['payroll.approve', 'Payroll to approve', PayrollRun::where('status', PayrollRun::DRAFT)->count(), 'admin.hr.payroll'],
            ['payroll.pay', 'Payroll to pay', PayrollRun::where('status', PayrollRun::APPROVED)->count(), 'admin.hr.payroll'],
        ];

        return collect($items)
            ->filter(fn ($i) => $user->can($i[0]) && $i[2] > 0)
            ->map(fn ($i) => ['label' => $i[1], 'count' => $i[2], 'route' => $i[3]])
            ->values()->all();
    }
}
