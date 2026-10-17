@php use App\Support\Money; @endphp
<x-layout title="Dashboard">
    <h1 class="mb-6 text-2xl font-black text-brand-700">Back office</h1>

    @if($schedulerWarning)
        <div class="mb-6 rounded border border-red-300 bg-red-50 px-4 py-3 font-bold text-red-800">⚠ {{ $schedulerWarning }}</div>
    @endif

    @if($mailWarning)
        <div class="mb-6 rounded border border-amber-300 bg-amber-50 px-4 py-3 font-bold text-amber-900">✉ {{ $mailWarning }}</div>
    @endif

    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Waiting for you</h2>
        @forelse($queue as $item)
            <a href="{{ route($item['route']) }}" class="flex items-center justify-between border-b py-2 last:border-0 hover:bg-gray-50">
                <span>{{ $item['label'] }}</span>
                <span class="rounded-full bg-gold-500 px-3 py-1 text-sm font-black text-brand-800">{{ $item['count'] }}</span>
            </a>
        @empty
            <p class="text-sm text-gray-500">Nothing is waiting for you right now.</p>
        @endforelse
    </x-card>

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <x-card><p class="text-sm text-gray-500">Active investments</p><p class="text-2xl font-black">{{ $activeInvestments }}</p></x-card>
    </div>
    @if($balances)
        <x-card>
            <h2 class="mb-3 font-black text-brand-700">Funds by account</h2>
            <table class="w-full text-sm"><tbody>
                @foreach($balances as $name => $amount)
                    <tr class="border-b"><td class="py-2">{{ $name }}</td><td class="text-right font-mono">{{ Money::format($amount) }}</td></tr>
                @endforeach
                <tr><td class="py-2 font-bold">Owed to investors (capital held)</td><td class="text-right font-mono font-bold">{{ Money::format($investorFunds) }}</td></tr>
            </tbody></table>
        </x-card>
    @endif
</x-layout>
