@php use App\Support\Money; @endphp
<x-layout title="Profit distributions">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Monthly profit distributions</h1>
    <p class="mb-6 text-sm text-gray-600">
        Next calculation: <strong>{{ $next['cutoff']->format('l d F Y') }}</strong> (end of day) ·
        investors must be paid by <strong>{{ $next['pay_by']->format('d F Y') }}</strong>.
        Split: 20% retained, then 30% investors / 70% company.
    </p>

    @can('payouts.prepare')
        <x-card class="mb-6">
            <form method="POST" action="{{ route('admin.distributions.calculate') }}" class="flex flex-wrap items-end gap-3">@csrf
                <x-field name="month" label="Calculate / recalculate month" type="month" :value="now()->format('Y-m')" required />
                <x-btn>Calculate</x-btn>
            </form>
        </x-card>
    @endcan

    <x-card>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr>
            <th class="py-2">Month</th><th>Status</th><th>Net profit</th><th>Retained</th><th>Investors (30%)</th><th>Company (70%)</th><th>Pay by</th><th></th></tr></thead><tbody>
        @forelse($runs as $run)
            <tr class="border-b last:border-0">
                <td class="py-2 font-bold">{{ $run->label() }}</td>
                <td>{{ ucfirst($run->status) }} @if($run->isOverdue()) <span class="rounded bg-red-100 px-1 text-xs font-bold text-red-700">OVERDUE</span> @endif</td>
                <td>{{ Money::format($run->total_net_profit) }}</td><td>{{ Money::format($run->total_retained) }}</td>
                <td>{{ Money::format($run->total_investor) }}</td><td>{{ Money::format($run->total_company) }}</td>
                <td>{{ $run->pay_by->format('d M Y') }}</td>
                <td><a class="text-brand-700 underline" href="{{ route('admin.distributions.show', $run) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="py-6 text-center text-gray-500">No runs yet. The first is calculated automatically on the 27th.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>
</x-layout>
