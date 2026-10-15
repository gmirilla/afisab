@php use App\Support\Money; @endphp
<x-layout title="Stock">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Stock</h1>
    <p class="mb-4 text-sm text-gray-600">Every lot received into the warehouse, across all cycles, with quality-check results.</p>
    <x-card>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr>
            <th class="py-2">Lot</th><th>Commodity</th><th>Cycle</th><th>Supplier</th><th>Warehouse</th><th>Grade / moisture</th><th>QA</th><th class="text-right">Received (kg)</th><th class="text-right">Remaining (kg)</th><th class="text-right">Remaining cost</th></tr></thead><tbody>
        @forelse($lots as $l)
            <tr class="border-b last:border-0">
                <td class="py-2">#{{ $l->id }}</td><td>{{ $l->commodity->name }}</td>
                <td><a class="text-brand-700 underline" href="{{ route('admin.trading.cycles.show', $l->cycle) }}">{{ $l->cycle->reference }}</a></td>
                <td>{{ $l->purchase->supplier->name }}</td><td>{{ $l->warehouse ?: '—' }}</td>
                <td>{{ $l->grade ?: '—' }} / {{ $l->moisture_percent !== null ? $l->moisture_percent.'%' : '—' }}</td>
                <td>{!! $l->qa_passed ? '<span class="text-green-700">Passed</span>' : '<span class="font-bold text-red-700">Failed</span>' !!}</td>
                <td class="text-right font-mono">{{ number_format($l->quantity_kg) }}</td><td class="text-right font-mono">{{ number_format($l->remaining_kg) }}</td>
                <td class="text-right font-mono">{{ Money::format($l->remaining_cost) }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="py-6 text-center text-gray-500">No stock yet.</td></tr>
        @endforelse
        </tbody>
        @if($lots->isNotEmpty())<tfoot class="border-t font-bold"><tr><td colspan="8" class="py-2">Total in warehouse</td><td class="text-right font-mono">{{ number_format($lots->sum('remaining_kg')) }}</td><td class="text-right font-mono">{{ Money::format($lots->sum('remaining_cost')) }}</td></tr></tfoot>@endif
        </table></div>
    </x-card>
</x-layout>
