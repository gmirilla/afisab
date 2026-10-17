<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvestmentStatus;
use App\Enums\KycStatus;
use App\Enums\ProfitFrequency;
use App\Http\Controllers\Controller;
use App\Models\CapitalPayout;
use App\Models\DistributionLine;
use App\Models\Investment;
use App\Models\Payment;
use App\Models\User;
use App\Services\Performance\PerformanceService;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only directory of investors for back-office staff (permission investors.view): who they are, their KYC
 * state and every policy, payment, profit payment and payout. Nothing here changes data.
 *
 * Full BVN and the ID document are for KYC reviewers only; everyone else sees the BVN masked. Opening a record
 * and exporting the list are written to the audit trail, because they expose personal data.
 */
class InvestorDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $users = $this->query($filters)->paginate(25)->withQueryString();
        $money = $this->moneyFor($users->getCollection());

        return view('admin.investors.index', [
            'users' => $users,
            'money' => $money,
            'filters' => $filters,
            'frequencies' => ProfitFrequency::cases(),
            'summary' => [
                'investors' => User::role('investor')->count(),
                'kyc_pending' => User::role('investor')->whereHas('investorProfile', fn ($q) => $q->where('kyc_status', KycStatus::Submitted))->count(),
                'with_active' => User::role('investor')->whereHas('investments', fn ($q) => $q->where('status', InvestmentStatus::Active))->count(),
                'principal' => (int) Investment::where('status', InvestmentStatus::Active)->sum('amount'),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $users = $this->query($this->filters($request))->get();
        $money = $this->moneyFor($users);
        activity()->causedBy($request->user())->withProperties(['rows' => $users->count()])->log('Investor list exported');

        $rows = [['Name', 'Email', 'Phone', 'KYC', 'Policies', 'Active policies', 'Active principal (NGN)', 'Profit paid (NGN)', 'Profit payable (NGN)', 'Registered']];
        foreach ($users as $u) {
            $m = $money[$u->id];
            $rows[] = [
                $u->name, $u->email, (string) $u->investorProfile?->phone, ($u->investorProfile?->kyc_status ?? KycStatus::Incomplete)->label(),
                $u->investments_count, $u->active_count, number_format((int) $u->active_principal / 100, 2, '.', ''),
                number_format($m['paid'] / 100, 2, '.', ''), number_format($m['payable'] / 100, 2, '.', ''), $u->created_at->toDateString(),
            ];
        }

        return Csv::download('investors.csv', $rows);
    }

    public function show(Request $request, User $user, PerformanceService $performance): View
    {
        abort_unless($user->hasRole('investor'), 404);
        $user->load('investorProfile');
        $viewer = $request->user();

        // Record who looked, but not every refresh.
        $recent = Activity::where('description', 'Investor record viewed')->where('causer_id', $viewer->id)
            ->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)->where('created_at', '>=', now()->subMinutes(30))->exists();
        if (! $recent) {
            activity()->performedOn($user)->causedBy($viewer)->log('Investor record viewed');
        }

        $investments = $user->investments()->with('plan')->orderByDesc('id')->get();
        $ids = $investments->pluck('id');
        $lines = DistributionLine::with(['run', 'investment'])->whereIn('investment_id', $ids)->orderByDesc('id')->get();

        $positions = $investments->mapWithKeys(fn (Investment $i) => [$i->id => $performance->position($i)]);

        return view('admin.investors.show', [
            'user' => $user,
            'profile' => $user->investorProfile,
            'investments' => $investments,
            'positions' => $positions,
            'payments' => Payment::with('investment')->whereIn('investment_id', $ids)->orderByDesc('id')->get(),
            'lines' => $lines->filter(fn ($l) => in_array($l->status, [DistributionLine::PAYABLE, DistributionLine::PAID], true)),
            'payouts' => CapitalPayout::with('investment')->whereIn('investment_id', $ids)->orderByDesc('id')->get(),
            'totals' => [
                'principal' => (int) $investments->whereIn('status', [InvestmentStatus::Active, InvestmentStatus::Matured])->sum('amount'),
                'paid' => (int) $lines->where('status', DistributionLine::PAID)->sum('investor_share'),
                'payable' => (int) $lines->where('status', DistributionLine::PAYABLE)->sum('investor_share'),
                'value' => (int) $investments->whereIn('status', [InvestmentStatus::Active, InvestmentStatus::Matured])->sum(fn ($i) => $positions[$i->id]['current_value']),
            ],
            'showFullBvn' => $viewer->can('kyc.review'),
            'activity' => $viewer->can('audit.view') ? $this->activityFor($user, $investments, $ids) : null,
        ]);
    }

    /** @return array{q: ?string, kyc: ?string, status: ?string, frequency: ?string, sort: string} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kyc' => ['nullable', Rule::enum(KycStatus::class)],
            'status' => ['nullable', Rule::in(['active', 'none'])],
            'frequency' => ['nullable', Rule::enum(ProfitFrequency::class)],
            'sort' => ['nullable', Rule::in(['newest', 'name', 'invested'])],
        ]);

        return [
            'q' => $data['q'] ?? null, 'kyc' => $data['kyc'] ?? null, 'status' => $data['status'] ?? null,
            'frequency' => $data['frequency'] ?? null, 'sort' => $data['sort'] ?? 'newest',
        ];
    }

    private function query(array $f): Builder
    {
        $like = $f['q'] ? '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($f['q'])).'%' : null;

        return User::role('investor')
            ->with('investorProfile')
            ->withCount([
                'investments',
                'investments as active_count' => fn ($q) => $q->where('status', InvestmentStatus::Active),
            ])
            ->withSum(['investments as active_principal' => fn ($q) => $q->whereIn('status', [InvestmentStatus::Active, InvestmentStatus::Matured])], 'amount')
            ->when($like, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', $like)->orWhere('email', 'like', $like)
                ->orWhereHas('investorProfile', fn ($p) => $p->where('phone', 'like', $like))
                ->orWhereHas('investments', fn ($i) => $i->where('reference', 'like', $like))))
            ->when($f['kyc'], fn ($q, $kyc) => $kyc === KycStatus::Incomplete->value
                ? $q->where(fn ($w) => $w->whereDoesntHave('investorProfile')->orWhereHas('investorProfile', fn ($p) => $p->where('kyc_status', $kyc)))
                : $q->whereHas('investorProfile', fn ($p) => $p->where('kyc_status', $kyc)))
            ->when($f['status'] === 'active', fn ($q) => $q->whereHas('investments', fn ($i) => $i->where('status', InvestmentStatus::Active)))
            ->when($f['status'] === 'none', fn ($q) => $q->whereDoesntHave('investments', fn ($i) => $i->whereIn('status', [InvestmentStatus::Active, InvestmentStatus::Matured])))
            ->when($f['frequency'], fn ($q, $freq) => $q->whereHas('investments', fn ($i) => $i->where('profit_frequency', $freq)))
            ->when($f['sort'] === 'name', fn ($q) => $q->orderBy('name'))
            ->when($f['sort'] === 'invested', fn ($q) => $q->orderByDesc('active_principal')->orderBy('name'))
            ->when($f['sort'] === 'newest', fn ($q) => $q->orderByDesc('id'));
    }

    /**
     * Profit paid and payable per investor, for just the investors on screen.
     *
     * @return array<int, array{paid: int, payable: int}>
     */
    private function moneyFor(Collection $users): array
    {
        $lines = DistributionLine::query()
            ->join('investments', 'investments.id', '=', 'distribution_lines.investment_id')
            ->whereIn('investments.user_id', $users->pluck('id'))
            ->whereIn('distribution_lines.status', [DistributionLine::PAID, DistributionLine::PAYABLE])
            ->get(['investments.user_id', 'distribution_lines.status', 'distribution_lines.investor_share'])
            ->groupBy('user_id');

        return $users->mapWithKeys(fn (User $u) => [$u->id => [
            'paid' => (int) ($lines[$u->id] ?? collect())->where('status', DistributionLine::PAID)->sum('investor_share'),
            'payable' => (int) ($lines[$u->id] ?? collect())->where('status', DistributionLine::PAYABLE)->sum('investor_share'),
        ]])->all();
    }

    private function activityFor(User $user, Collection $investments, Collection $investmentIds): Collection
    {
        $payments = Payment::whereIn('investment_id', $investmentIds)->pluck('id');
        $profileId = $user->investorProfile?->id;

        return Activity::with('causer')
            ->where(function ($q) use ($user, $investmentIds, $payments, $profileId) {
                $q->where(fn ($w) => $w->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id))
                    ->orWhere(fn ($w) => $w->where('subject_type', (new Investment)->getMorphClass())->whereIn('subject_id', $investmentIds))
                    ->orWhere(fn ($w) => $w->where('subject_type', (new Payment)->getMorphClass())->whereIn('subject_id', $payments));
                if ($profileId) {
                    $q->orWhere(fn ($w) => $w->where('subject_type', (new \App\Models\InvestorProfile)->getMorphClass())->where('subject_id', $profileId));
                }
            })
            ->orderByDesc('id')->limit(40)->get();
    }
}
