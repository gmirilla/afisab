@php use App\Support\Money; @endphp
<x-layout title="Trading cycles report">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-black text-brand-700">Trading cycles</h1>
        <div class="flex gap-3 text-sm"><a class="underline" href="{{ route('admin.reports.index') }}">← Reports</a>
            <a class="rounded bg-brand-700 px-3 py-1 font-bold text-white" href="{{ route('admin.reports.cycles', ['format' => 'csv']) }}">Download CSV</a></div>
    </div>
    <x-card><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr>
        <th class="py-2">Cycle</th><th>Commodity</th><th>Status</th><th class="text-right">Budget</th><th class="text-right">Purchases</th><th class="text-right">Expenses</th><th class="text-right">Sales</th><th class="text-right">Net profit</th><th class="text-right">Return</th></tr></thead><tbody>
        @forelse($rows as $r)
            <tr class="border-b last:border-0"><td class="py-2">{{ $r['name'] }}<br><span class="font-mono text-xs text-gray-500">{{ $r['reference'] }}</span></td><td>{{ $r['commodity'] }}</td><td>{{ $r['status'] }}</td>
                <td class="text-right font-mono">{{ Money::format($r['budget']) }}</td><td class="text-right font-mono">{{ Money::format($r['purchases']) }}</td>
                <td class="text-right font-mono">{{ Money::format($r['expenses']) }}</td><td class="text-right font-mono">{{ Money::format($r['sales']) }}</td>
                <td class="text-right font-mono">{{ $r['net_profit'] === null ? '—' : Money::format($r['net_profit']) }}</td>
                <td class="text-right">{{ $r['return_bps'] === null ? '—' : number_format($r['return_bps'] / 100, 2).'%' }}</td></tr>
        @empty <tr><td colspan="9" class="py-6 text-center text-gray-500">No trading cycles.</td></tr> @endforelse
    </tbody></table></div></x-card>
</x-layout>
