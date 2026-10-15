@php use App\Support\Money; @endphp
<x-layout title="Risk">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Risk controls</h1>
    <p class="mb-6 text-sm text-gray-600">Limits are checked when capital is allocated to a cycle and when a purchase is approved. A limit that is switched off is not enforced.</p>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <x-card><p class="text-xs text-gray-500">Investor capital at work</p><p class="text-xl font-black">{{ number_format($dash['deployment_bps'] / 100, 1) }}%</p>
            <p class="text-xs text-gray-500">{{ Money::format($dash['deployed']) }} of {{ Money::format($dash['capital']) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">Risk Fund cover of investor capital</p><p class="text-xl font-black">{{ number_format($dash['risk_cover_bps'] / 100, 1) }}%</p>
            <p class="text-xs text-gray-500">{{ Money::format($dash['risk_fund']) }} in the Risk Fund</p></x-card>
        <x-card class="{{ $dash['liquidity']['available'] < 0 ? 'border-red-300 bg-red-50' : '' }}"><p class="text-xs text-gray-500">Cash available after 30-day obligations</p><p class="text-xl font-black {{ $dash['liquidity']['available'] < 0 ? 'text-red-700' : '' }}">{{ Money::format($dash['liquidity']['available']) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">Takaful cover in force</p><p class="text-xl font-black">{{ Money::format($dash['takaful_cover']) }}</p>
            <p class="text-xs {{ $dash['takaful_current'] ? 'text-gray-500' : 'font-bold text-red-700' }}">{{ $dash['takaful_current'] }} current {{ \Illuminate\Support\Str::plural('policy', $dash['takaful_current']) }}</p></x-card>
    </div>

    @foreach($dash['takaful_expiring'] as $p)
        <div class="mb-3 rounded border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900">Takaful policy <strong>{{ $p->policy_number }}</strong> ({{ $p->provider }}) expires on {{ $p->end_date->format('d M Y') }}.</div>
    @endforeach

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        @foreach([['Largest supplier exposures', $dash['suppliers']], ['Largest commodity exposures', $dash['commodities']], ['Open cycles by size', $dash['cycles']]] as [$title, $rows])
            <x-card><h2 class="mb-2 font-black text-brand-700">{{ $title }}</h2>
                @forelse($rows->take(5) as $r)<div class="flex justify-between border-b py-1 text-sm last:border-0"><span>{{ $r['name'] }}</span><span class="font-mono">{{ Money::format($r['amount']) }}</span></div>
                @empty <p class="text-sm text-gray-500">None.</p> @endforelse
            </x-card>
        @endforeach
    </div>

    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Limits</h2>
        <form method="POST" action="{{ route('admin.risk.limits') }}" class="space-y-3">@csrf
            @foreach($definitions as $key => $def)
                @php($value = $dash['limits'][$key] ?? null)
                <div class="grid items-end gap-3 border-b pb-3 md:grid-cols-12">
                    <div class="md:col-span-6"><p class="font-bold">{{ $def['label'] }}</p><p class="text-xs text-gray-500">{{ $def['help'] }} Suggested: {{ $def['unit'] === 'percent' ? number_format($def['suggested'] / 100, 0).'%' : Money::format($def['suggested']) }}</p></div>
                    <div class="md:col-span-4">
                        <label class="block text-sm text-gray-700">{{ $def['unit'] === 'percent' ? 'Percent (%)' : 'Amount (₦)' }}
                            <input name="limits[{{ $key }}][value]" value="{{ $value === null ? '' : ($def['unit'] === 'percent' ? $value / 100 : number_format($value / 100, 0, '.', '')) }}" @cannot('risk.manage') disabled @endcannot inputmode="decimal" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2"></label>
                    </div>
                    <label class="flex items-center gap-2 pb-2 text-sm md:col-span-2"><input type="checkbox" name="limits[{{ $key }}][active]" value="1" @checked(in_array($key, $dash['active'])) @cannot('risk.manage') disabled @endcannot> Enforce</label>
                </div>
            @endforeach
            @can('risk.manage')<x-btn>Save limits</x-btn>@endcan
        </form>
    </x-card>

    <x-card>
        <h2 class="mb-3 font-black text-brand-700">Takaful policies</h2>
        <p class="mb-3 text-xs text-gray-500">Premiums are paid from the Investors Risk Fund.</p>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Policy</th><th>Provider</th><th>Period</th><th class="text-right">Cover</th><th class="text-right">Premium</th><th>Premium status</th></tr></thead><tbody>
            @forelse($policies as $p)
                <tr class="border-b align-top last:border-0"><td class="py-2 font-mono">{{ $p->policy_number }}<br><span class="font-sans text-xs text-gray-500">{{ $p->description }}</span></td><td>{{ $p->provider }}</td>
                    <td>{{ $p->start_date->format('d M Y') }} to {{ $p->end_date->format('d M Y') }}</td><td class="text-right font-mono">{{ Money::format($p->cover_amount) }}</td><td class="text-right font-mono">{{ Money::format($p->premium) }}</td>
                    <td>@if($p->premium_status === 'paid') Paid {{ $p->paid_at?->format('d M Y') }} {{ $p->paid_reference ? '('.$p->paid_reference.')' : '' }}
                        @else @can('takaful.manage')<form method="POST" action="{{ route('admin.risk.takaful.pay', $p) }}" class="flex gap-1">@csrf<input name="reference" placeholder="Bank ref" required class="w-24 rounded border px-2 py-1 text-xs"><x-btn>Pay premium</x-btn></form>@else Unpaid @endcan @endif</td></tr>
            @empty <tr><td colspan="6" class="py-4 text-center text-gray-500">No Takaful policies recorded.</td></tr> @endforelse
        </tbody></table>
        @can('takaful.manage')
            <form method="POST" action="{{ route('admin.risk.takaful') }}" class="mt-4 grid gap-3 border-t pt-4 md:grid-cols-4">@csrf
                <x-field name="provider" label="Provider" required /><x-field name="policy_number" label="Policy number" required /><x-field name="description" label="What it covers" required />
                <x-field name="cover_amount" label="Cover amount (₦)" inputmode="decimal" required /><x-field name="premium" label="Premium (₦)" inputmode="decimal" required />
                <x-field name="start_date" label="Start" type="date" required /><x-field name="end_date" label="End" type="date" required />
                <div class="flex items-end"><x-btn>Record policy</x-btn></div>
            </form>
        @endcan
    </x-card>
</x-layout>
