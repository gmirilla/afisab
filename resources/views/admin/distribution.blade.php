@php use App\Support\Money; use App\Models\DistributionRun; use App\Models\DistributionLine; @endphp
<x-layout :title="'Distribution '.$run->period">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-black text-brand-700">{{ $run->label() }} distribution
            <span class="ml-2 rounded bg-brand-50 px-2 py-1 text-sm">{{ ucfirst($run->status) }}</span>
            @if($run->isOverdue()) <span class="rounded bg-red-100 px-2 py-1 text-sm font-bold text-red-700">OVERDUE</span> @endif</h1>
        <a class="text-sm underline" href="{{ route('admin.distributions') }}">← All runs</a>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <x-card><p class="text-sm text-gray-500">Net profit</p><p class="text-xl font-black">{{ Money::format($run->total_net_profit) }}</p></x-card>
        <x-card><p class="text-sm text-gray-500">Retained (20%)</p><p class="text-xl font-black">{{ Money::format($run->total_retained) }}</p></x-card>
        <x-card><p class="text-sm text-gray-500">Investors (30% of rest)</p><p class="text-xl font-black text-brand-700">{{ Money::format($run->total_investor) }}</p></x-card>
        <x-card><p class="text-sm text-gray-500">Company (70% of rest)</p><p class="text-xl font-black">{{ Money::format($run->total_company) }}</p></x-card>
    </div>

    @if($run->policies_deferred > 0)
        <p class="mb-4 rounded border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">{{ $run->policies_deferred }} {{ \Illuminate\Support\Str::plural('policy', $run->policies_deferred) }} with trading results {{ $run->policies_deferred === 1 ? 'is' : 'are' }} <strong>not due this month</strong> (quarterly or one-off payment). The results stay recorded and are netted into the run where they fall due.</p>
    @endif

    <x-card class="mb-6 text-sm">
        Cut-off: results recorded up to the end of <strong>{{ $run->cutoff_date->format('d M Y') }}</strong> · pay investors by <strong>{{ $run->pay_by->format('d M Y') }}</strong><br>
        Calculated {{ $run->calculated_at?->format('d M Y H:i') }} {{ $run->calculator ? 'by '.$run->calculator->name : '(automatically)' }}
        @if($run->approved_at) · Approved {{ $run->approved_at->format('d M Y H:i') }} by {{ $run->approver?->name }} @endif
        @if($run->paid_at) · Fully paid {{ $run->paid_at->format('d M Y H:i') }} @endif

        <div class="mt-4 flex flex-wrap items-end gap-3">
            @if($run->status === DistributionRun::CALCULATED)
                @can('payouts.prepare')
                    <form method="POST" action="{{ route('admin.distributions.recalculate', $run) }}">@csrf<x-btn variant="ghost">Recalculate</x-btn></form>
                @endcan
                @can('payouts.approve')
                    <form method="POST" action="{{ route('admin.distributions.approve', $run) }}"
                          data-confirm="Approve this run? This books {{ Money::format($run->total_investor) }} as owed to investors.">@csrf<x-btn>Approve run</x-btn></form>
                @endcan
            @endif
            @if($run->status !== DistributionRun::CALCULATED)
                @can('payouts.prepare')
                    <a class="rounded border border-brand-700 px-4 py-2 text-sm font-bold text-brand-700" href="{{ route('admin.distributions.export', $run) }}">Download bank CSV</a>
                @endcan
            @endif
            @if($run->status === DistributionRun::APPROVED)
                @can('payouts.prepare')
                    <form method="POST" action="{{ route('admin.distributions.pay', $run) }}" class="flex items-end gap-2">@csrf
                        <x-field name="reference" label="Bank batch reference" required />
                        <x-btn>Mark all paid</x-btn>
                    </form>
                @endcan
            @endif
        </div>
    </x-card>

    <x-card>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr>
            <th class="py-2">Policy</th><th>Investor</th><th>Paid</th><th>Net profit</th><th>Retained</th><th>Investor share</th><th>Company share</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($lines as $l)
            <tr class="border-b align-top last:border-0">
                <td class="py-2 font-mono">{{ $l->investment->reference }}</td><td>{{ $l->investment->user->name }}</td>
                <td>{{ $l->frequency ? \App\Enums\ProfitFrequency::tryFrom($l->frequency)?->label() : 'Monthly' }}@if($l->coverage())<br><span class="text-xs text-gray-500">covers {{ $l->coverage() }}</span>@endif</td>
                <td>{{ Money::format($l->net_profit) }}@if($l->net_profit < 0) <span class="text-xs text-red-700">(loss)</span>@endif</td>
                <td>{{ Money::format($l->retained) }}</td><td class="font-bold">{{ Money::format($l->investor_share) }}</td><td>{{ Money::format($l->company_share) }}</td>
                <td>{{ ucfirst($l->status) }}@if($l->payment_reference)<br><span class="text-xs text-gray-500">{{ $l->payment_reference }}</span>@endif</td>
                <td>
                    @if($l->status === DistributionLine::PAYABLE)
                        @can('payouts.prepare')
                            <form method="POST" action="{{ route('admin.distributions.pay-line', $l) }}" class="flex gap-1">@csrf
                                <input name="reference" placeholder="Reference" required class="w-28 rounded border px-2 py-1 text-xs"><x-btn>Paid</x-btn></form>
                        @endcan
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="py-6 text-center text-gray-500">No trading results fell in this period.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>
</x-layout>
