@php use App\Support\Money; use App\Models\TradeCycle; @endphp
<x-layout title="Trading cycles">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Trading cycles</h1>
    <p class="mb-6 text-sm text-gray-600">A cycle buys one commodity with allocated investor capital, stores it, sells it, and closes. Its profit then flows into the monthly distribution.</p>

    @can('cycles.manage')
        <x-card class="mb-6">
            <form method="POST" action="{{ route('admin.trading.cycles.store') }}" class="grid gap-3 md:grid-cols-4 md:items-end">@csrf
                <x-field name="name" label="Cycle name" placeholder="e.g. Sesame, Nov harvest" required />
                <label class="block text-sm font-bold text-gray-700">Commodity
                    <select name="commodity_id" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 font-normal">
                        @foreach($commodities as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select></label>
                <x-field name="notes" label="Notes" />
                <x-btn>Open cycle</x-btn>
            </form>
        </x-card>
    @endcan

    <x-card>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr>
            <th class="py-2">Reference</th><th>Name</th><th>Commodity</th><th>Status</th><th class="text-right">Budget</th><th class="text-right">Net profit</th><th></th></tr></thead><tbody>
        @forelse($cycles as $c)
            <tr class="border-b last:border-0">
                <td class="py-2 font-mono">{{ $c->reference }}</td><td>{{ $c->name }}</td><td>{{ $c->commodity->name }}</td>
                <td>{{ ucfirst($c->status) }}</td>
                <td class="text-right font-mono">{{ Money::format((int) $c->budget) }}</td>
                <td class="text-right font-mono">{{ $c->status === TradeCycle::CLOSED ? Money::format($c->net_profit) : '—' }}</td>
                <td><a class="text-brand-700 underline" href="{{ route('admin.trading.cycles.show', $c) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-6 text-center text-gray-500">No trading cycles yet.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>
</x-layout>
