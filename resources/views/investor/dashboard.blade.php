@php use App\Support\Money; use App\Enums\KycStatus; use App\Enums\InvestmentStatus; @endphp
<x-layout title="My investments">
    @if($profile->kyc_status !== KycStatus::Approved)
        <div class="mb-6 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
            Identity verification: <strong>{{ $profile->kyc_status->label() }}</strong>.
            <a class="underline" href="{{ route('investor.profile') }}">Complete your profile &amp; KYC</a> before you can invest.
            @if($profile->kyc_note) <br>Reviewer note: {{ $profile->kyc_note }} @endif
        </div>
    @endif

    <p class="mb-4 text-sm text-gray-600">Profit is calculated on the <strong>{{ $next['cutoff']->format('jS \o\f F') }}</strong> of each month and each policy is paid by the end of the month in which its payment frequency falls due (shown on each policy below). Returns are a share of actual trading profit and are not guaranteed.</p>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <x-card><p class="text-sm text-gray-500">Total invested (active)</p><p class="text-2xl font-black text-brand-700">{{ Money::format($totalInvested) }}</p></x-card>
        <x-card><p class="text-sm text-gray-500">Current value</p><p class="text-2xl font-black text-brand-700">{{ Money::format($totalValue) }}</p>
            <p class="text-xs text-gray-500">Principal plus approved profit awaiting payment</p></x-card>
        <x-card><p class="text-sm text-gray-500">Active investments</p><p class="text-2xl font-black text-brand-700">{{ $activeCount }}</p></x-card>
        <x-card class="flex items-center"><a href="{{ route('investor.plans') }}" class="w-full rounded bg-gold-500 px-4 py-3 text-center font-bold text-brand-800">New investment</a></x-card>
    </div>

    <div class="space-y-4">
        @forelse($investments as $i)
            @php($pos = $positions[$i->id])
            <x-card>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-black text-brand-700">{{ $i->plan->name }} <span class="font-mono text-sm font-normal text-gray-500">{{ $i->reference }}</span></p>
                        <p class="text-sm text-gray-600">{{ $i->status->label() }} · {{ $i->start_date?->format('d M Y') ?? 'Not started' }} → {{ $i->maturity_date?->format('d M Y') ?? '—' }}</p>
                        <p class="text-sm text-gray-600">Profit paid: <strong>{{ $i->profit_frequency->label() }}</strong>@if($nextRuns[$i->id] ?? null) · next by {{ $nextRuns[$i->id]['pay_by']->format('d M Y') }}@endif</p>
                    </div>
                    <a class="rounded border border-brand-700 px-3 py-1 text-sm font-bold text-brand-700" href="{{ route('investor.investments.show', $i) }}">View</a>
                </div>
                <div class="mt-3 grid gap-3 text-sm md:grid-cols-4">
                    <div><span class="text-gray-500">Principal</span><br><strong>{{ Money::format($i->amount) }}</strong></div>
                    <div><span class="text-gray-500">Profit received / payable</span><br><strong>{{ Money::format($pos['paid']) }}</strong> / {{ Money::format($pos['payable']) }}</div>
                    <div><span class="text-gray-500">Current value</span><br><strong>{{ Money::format($pos['current_value']) }}</strong></div>
                    <div><span class="text-gray-500">Trading cycles</span><br><strong>{{ $pos['cycles'] }}</strong>
                        @if($pos['pending_entries']) <span class="text-xs text-amber-700">({{ $pos['pending_entries'] }} pending allocation)</span>@endif</div>
                </div>
                @if($i->status === InvestmentStatus::Active)
                    <div class="mt-3">
                        <div class="h-2 overflow-hidden rounded bg-gray-200"><div class="h-2 bg-brand-600" style="width: {{ $i->progressPercent() }}%"></div></div>
                        <p class="mt-1 text-xs text-gray-500">{{ $i->daysToMaturity() }} days to maturity</p>
                    </div>
                @endif
            </x-card>
        @empty
            <x-card class="py-8 text-center text-gray-500">No investments yet.</x-card>
        @endforelse
    </div>
</x-layout>
