@php use App\Support\Money; use App\Enums\InvestmentStatus; @endphp
<x-layout title="Funds">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Funds</h1>
    <p class="mb-6 text-sm text-gray-600">Balances come straight from the ledger. "Available" is what Main Operations holds beyond what is owed in the next 30 days.</p>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <x-card><p class="text-sm text-gray-500">Main Operations</p><p class="text-xl font-black">{{ Money::format($overview['main_ops']) }}</p></x-card>
        <x-card><p class="text-sm text-gray-500">Due within 30 days</p><p class="text-xl font-black">{{ Money::format($overview['obligations_due']) }}</p>
            <p class="text-xs text-gray-500">Principal {{ Money::format($overview['principal_due']) }} + profit payable {{ Money::format($overview['profit_payable']) }}</p></x-card>
        <x-card class="{{ $overview['available'] < 0 ? 'border-red-300 bg-red-50' : '' }}"><p class="text-sm text-gray-500">Available after obligations</p>
            <p class="text-xl font-black {{ $overview['available'] < 0 ? 'text-red-700' : 'text-brand-700' }}">{{ Money::format($overview['available']) }}</p>
            @if($overview['available'] < 0)<p class="text-xs font-bold text-red-700">Shortfall: obligations exceed Main Operations cash.</p>@endif</x-card>
        <x-card><p class="text-sm text-gray-500">Investor capital held</p><p class="text-xl font-black">{{ Money::format($overview['investor_capital_held']) }}</p></x-card>
    </div>

    @can('ledger.view')
    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Ledger accounts</h2>
        <table class="w-full text-sm"><tbody>
            @foreach($accounts as $a)
                <tr class="border-b last:border-0"><td class="py-2"><a class="text-brand-700 underline" href="{{ route('admin.ledger.account', $a->code) }}">{{ $a->name }}</a></td>
                    <td class="text-gray-500">{{ ucfirst($a->type) }}</td></tr>
            @endforeach
        </tbody></table>
    </x-card>
    @endcan

    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Principal falling due, by month</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Month</th><th>Policies</th><th class="text-right">Principal</th></tr></thead><tbody>
            @forelse($schedule as $m)
                <tr class="border-b last:border-0"><td class="py-2">{{ $m['label'] }}</td><td>{{ $m['count'] }}</td><td class="text-right font-mono">{{ Money::format($m['amount']) }}</td></tr>
            @empty <tr><td colspan="3" class="py-4 text-center text-gray-500">No active investments.</td></tr> @endforelse
        </tbody></table>
    </x-card>

    <x-card>
        <h2 class="mb-3 font-black text-brand-700">Funds by investment policy</h2>
        <div class="overflow-x-auto">
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr>
            <th class="py-2">Policy</th><th>Investor</th><th>Status</th><th>Matures</th><th class="text-right">Capital held</th><th class="text-right">In Main Ops</th><th class="text-right">In Risk Fund</th><th class="text-right">Profit payable</th><th class="text-right">Principal due ≤30d</th></tr></thead><tbody>
        @forelse($rows as $r)
            @php($i = $r['investment'])
            <tr class="border-b last:border-0">
                <td class="py-2 font-mono">{{ $i->reference }}</td><td>{{ $i->user->name }}</td><td>{{ $i->status->label() }}</td>
                <td>{{ $i->maturity_date?->format('d M Y') }}</td>
                <td class="text-right font-mono">{{ Money::format($r['capital_held']) }}</td>
                <td class="text-right font-mono">{{ Money::format($r['in_main_ops']) }}</td>
                <td class="text-right font-mono">{{ Money::format($r['in_risk_fund']) }}</td>
                <td class="text-right font-mono">{{ Money::format($r['profit_payable']) }}</td>
                <td class="text-right font-mono {{ $r['principal_due'] ? 'font-bold text-amber-700' : '' }}">{{ Money::format($r['principal_due']) }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="py-6 text-center text-gray-500">No active investment policies.</td></tr>
        @endforelse
        </tbody>
        @if($rows->isNotEmpty())
            <tfoot class="border-t font-bold"><tr><td colspan="4" class="py-2">Total</td>
                <td class="text-right font-mono">{{ Money::format($rows->sum('capital_held')) }}</td>
                <td class="text-right font-mono">{{ Money::format($rows->sum('in_main_ops')) }}</td>
                <td class="text-right font-mono">{{ Money::format($rows->sum('in_risk_fund')) }}</td>
                <td class="text-right font-mono">{{ Money::format($rows->sum('profit_payable')) }}</td>
                <td class="text-right font-mono">{{ Money::format($rows->sum('principal_due')) }}</td></tr></tfoot>
        @endif
        </table>
        </div>
    </x-card>
</x-layout>
