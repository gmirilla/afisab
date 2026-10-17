<?php

namespace App\Services\Risk;

use App\Enums\InvestmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Commodity;
use App\Models\CycleAllocation;
use App\Models\Investment;
use App\Models\LedgerAccount;
use App\Models\Purchase;
use App\Models\RiskLimit;
use App\Models\Supplier;
use App\Models\TakafulPolicy;
use App\Models\TradeCycle;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Treasury\TreasuryService;
use App\Support\Money;

/**
 * Owner-set limits, enforced where money is committed (purchase approval, capital allocation),
 * plus the numbers shown on the risk dashboard. A limit that is not active is not enforced.
 */
class RiskService
{
    /** @var array<string, array{label: string, unit: string, suggested: int, help: string}> */
    public const LIMITS = [
        'max_supplier_exposure' => [
            'label' => 'Maximum exposure to one supplier', 'unit' => 'money', 'suggested' => 10_000_000_00,
            'help' => 'Approved, paid or received purchases from one supplier in open cycles.',
        ],
        'max_commodity_exposure' => [
            'label' => 'Maximum exposure to one commodity', 'unit' => 'money', 'suggested' => 50_000_000_00,
            'help' => 'Approved, paid or received purchases of one commodity in open cycles.',
        ],
        'max_cycle_budget' => [
            'label' => 'Maximum size of one trading cycle', 'unit' => 'money', 'suggested' => 100_000_000_00,
            'help' => 'Total investor capital allocated to a single cycle.',
        ],
        'max_deployment_bps' => [
            'label' => 'Maximum share of investor capital at work', 'unit' => 'percent', 'suggested' => 8_500,
            'help' => 'Capital allocated to open cycles as a share of all active investor capital (keeps a liquidity buffer).',
        ],
        'purchase_director_threshold' => [
            'label' => 'Purchases above this need a Director', 'unit' => 'money', 'suggested' => 5_000_000_00,
            'help' => 'A single purchase above this amount can only be approved by a Director.',
        ],
    ];

    public function __construct(private LedgerService $ledger, private TreasuryService $treasury) {}

    /** The active value of a limit, or null when it is not enforced. */
    public function limit(string $key): ?int
    {
        return RiskLimit::where('key', $key)->where('is_active', true)->value('value');
    }

    public function directorThreshold(): int
    {
        return $this->limit('purchase_director_threshold') ?? (int) config('afisab.purchase_director_threshold');
    }

    public function save(string $key, ?int $value, bool $active, User $by): RiskLimit
    {
        if (! isset(self::LIMITS[$key])) {
            throw new BusinessRuleException('Unknown limit.');
        }
        if ($active && ($value === null || $value <= 0)) {
            throw new BusinessRuleException('An active limit needs a value greater than zero.');
        }
        if ($key === 'max_deployment_bps' && $value !== null && $value > 10_000) {
            throw new BusinessRuleException('The share of capital at work cannot exceed 100%.');
        }

        $limit = RiskLimit::updateOrCreate(['key' => $key], ['value' => $value, 'is_active' => $active, 'updated_by' => $by->id]);
        activity()->performedOn($limit)->causedBy($by)->withProperties(['key' => $key, 'value' => $value, 'active' => $active])->log('Risk limit changed');

        return $limit;
    }

    // ---- enforcement ----

    public function assertSupplierExposure(Supplier $supplier, int $additional): void
    {
        $limit = $this->limit('max_supplier_exposure');
        if ($limit !== null && $this->supplierExposure($supplier) + $additional > $limit) {
            throw new BusinessRuleException("This purchase would take exposure to {$supplier->name} to ".Money::format($this->supplierExposure($supplier) + $additional).', above the limit of '.Money::format($limit).'.');
        }
    }

    public function assertCommodityExposure(Commodity $commodity, int $additional): void
    {
        $limit = $this->limit('max_commodity_exposure');
        if ($limit !== null && $this->commodityExposure($commodity) + $additional > $limit) {
            throw new BusinessRuleException("This purchase would take exposure to {$commodity->name} to ".Money::format($this->commodityExposure($commodity) + $additional).', above the limit of '.Money::format($limit).'.');
        }
    }

    public function assertCycleBudget(int $newBudget): void
    {
        $limit = $this->limit('max_cycle_budget');
        if ($limit !== null && $newBudget > $limit) {
            throw new BusinessRuleException('A single cycle may not exceed '.Money::format($limit).' of investor capital.');
        }
    }

    /** $additional is the extra capital this allocation would put to work. */
    public function assertDeployment(int $additional): void
    {
        $limit = $this->limit('max_deployment_bps');
        if ($limit === null || $additional <= 0) {
            return;
        }

        $capital = $this->activeCapital();
        $after = $this->deployedCapital() + $additional;
        if ($capital > 0 && intdiv($after * 10_000, $capital) > $limit) {
            throw new BusinessRuleException('This would put '.number_format($after * 100 / max(1, $capital), 1).'% of investor capital to work; the limit is '.number_format($limit / 100, 1).'%.');
        }
    }

    // ---- measurements ----

    public function supplierExposure(Supplier $supplier): int
    {
        return (int) $this->openPurchases()->where('supplier_id', $supplier->id)->sum('total');
    }

    public function commodityExposure(Commodity $commodity): int
    {
        return (int) $this->openPurchases()->whereHas('cycle', fn ($q) => $q->where('commodity_id', $commodity->id))->sum('total');
    }

    public function deployedCapital(): int
    {
        // Capital reserved by a cycle still waiting for approval counts, otherwise the limit could be passed by proposing several at once.
        return (int) CycleAllocation::whereHas('cycle', fn ($q) => $q->whereIn('status', TradeCycle::HOLDING))->sum('amount');
    }

    public function activeCapital(): int
    {
        return (int) Investment::where('status', InvestmentStatus::Active)->sum('amount');
    }

    /**
     * Everything the risk dashboard shows.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $deployed = $this->deployedCapital();
        $capital = $this->activeCapital();
        $investorFunds = $this->ledger->balance(LedgerAccount::INVESTOR_FUNDS);
        $riskFund = $this->ledger->balance(LedgerAccount::RISK_FUND);

        $suppliers = Supplier::all()->map(fn (Supplier $s) => ['name' => $s->name, 'amount' => $this->supplierExposure($s)])->filter(fn ($r) => $r['amount'] > 0)->sortByDesc('amount')->values();
        $commodities = Commodity::all()->map(fn (Commodity $c) => ['name' => $c->name, 'amount' => $this->commodityExposure($c)])->filter(fn ($r) => $r['amount'] > 0)->sortByDesc('amount')->values();
        $cycles = TradeCycle::where('status', TradeCycle::OPEN)->get()->map(fn (TradeCycle $c) => ['name' => $c->name, 'amount' => $c->budget()])->sortByDesc('amount')->values();

        $policies = TakafulPolicy::orderBy('end_date')->get();

        return [
            'deployed' => $deployed,
            'capital' => $capital,
            'deployment_bps' => $capital > 0 ? intdiv($deployed * 10_000, $capital) : 0,
            'risk_fund' => $riskFund,
            'risk_cover_bps' => $investorFunds > 0 ? intdiv($riskFund * 10_000, $investorFunds) : 0,
            'liquidity' => $this->treasury->overview(),
            'suppliers' => $suppliers,
            'commodities' => $commodities,
            'cycles' => $cycles,
            'takaful_current' => $policies->filter(fn (TakafulPolicy $p) => $p->isCurrent())->count(),
            'takaful_expiring' => $policies->filter(fn (TakafulPolicy $p) => $p->expiresWithin(30)),
            'takaful_cover' => (int) $policies->filter(fn (TakafulPolicy $p) => $p->isCurrent())->sum('cover_amount'),
            'limits' => RiskLimit::pluck('value', 'key')->all(),
            'active' => RiskLimit::where('is_active', true)->pluck('key')->all(),
        ];
    }

    private function openPurchases()
    {
        return Purchase::whereIn('status', [Purchase::APPROVED, Purchase::PAID, Purchase::RECEIVED])
            ->whereHas('cycle', fn ($q) => $q->where('status', TradeCycle::OPEN));
    }
}
