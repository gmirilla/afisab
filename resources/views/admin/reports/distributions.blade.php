@php use App\Support\Money; @endphp
<x-layout title="Distributions report">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-black text-brand-700">Monthly profit distributions</h1>
        <div class="flex gap-3 text-sm"><a class="underline" href="{{ route('admin.reports.index') }}">← Reports</a>
            <a class="rounded bg-brand-700 px-3 py-1 font-bold text-white" href="{{ route('admin.reports.distributions', ['format' => 'csv']) }}">Download CSV</a></div>
    </div>
    <x-card><table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Month</th><th>Status</th><th class="text-right">Net profit</th><th class="text-right">Retained</th><th class="text-right">Investors</th><th class="text-right">Company</th><th>Pay by</th></tr></thead><tbody>
        @forelse($runs as $r)
            <tr class="border-b last:border-0"><td class="py-2">{{ $r->label() }}</td><td>{{ ucfirst($r->status) }}</td><td class="text-right font-mono">{{ Money::format($r->total_net_profit) }}</td>
                <td class="text-right font-mono">{{ Money::format($r->total_retained) }}</td><td class="text-right font-mono">{{ Money::format($r->total_investor) }}</td><td class="text-right font-mono">{{ Money::format($r->total_company) }}</td><td>{{ $r->pay_by->format('d M Y') }}</td></tr>
        @empty <tr><td colspan="7" class="py-6 text-center text-gray-500">No runs yet.</td></tr> @endforelse
    </tbody></table></x-card>
</x-layout>
