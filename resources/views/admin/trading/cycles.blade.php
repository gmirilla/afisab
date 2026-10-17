@php use App\Support\Money; use App\Models\TradeCycle; @endphp
<x-layout title="Trading cycles">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Trading cycles</h1>
    <p class="mb-6 text-sm text-gray-600">A cycle buys one commodity with allocated investor capital, stores it, sells it, and closes. Its profit then flows into the monthly distribution.</p>

    @if($errors->has('capital'))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('capital') }}</p>@endif
    @if($cashWarning)<p class="mb-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"><strong>Cash warning.</strong> {{ $cashWarning }}</p>@endif

    @can('cycles.manage')
        <x-card class="mb-6">
            <h2 class="mb-1 font-black text-brand-700">Propose a cycle</h2>
            <p class="mb-3 text-sm text-gray-600">Every cycle needs a Director's approval before anything can be bought. Capital is reserved as soon as you propose it, and released if it is rejected or cancelled.</p>
            <form method="POST" action="{{ route('admin.trading.cycles.store') }}" class="space-y-4">@csrf
                <div class="grid gap-3 md:grid-cols-3 md:items-end">
                    <x-field name="name" label="Cycle name" placeholder="e.g. Sesame, Nov harvest" required />
                    <label class="block text-sm font-bold text-gray-700">Commodity
                        <select name="commodity_id" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 font-normal">
                            @foreach($commodities as $c)<option value="{{ $c->id }}" @selected((int) old('commodity_id') === $c->id)>{{ $c->name }}</option>@endforeach
                        </select></label>
                    <x-field name="notes" label="Notes" />
                </div>

                <fieldset class="rounded-lg border border-stone-200 p-4">
                    <legend class="px-2 text-sm font-bold text-gray-700">Investor capital</legend>
                    <p class="mb-3 text-sm text-gray-600">Free capital now: <strong>{{ Money::format($pool['total']) }}</strong> across {{ count($pool['rows']) }} {{ \Illuminate\Support\Str::plural('policy', count($pool['rows'])) }}@if($pool['excluded']),
                        plus {{ Money::format(collect($pool['excluded'])->sum('free')) }} in {{ count($pool['excluded']) }} maturing within {{ $bufferDays }} days (left out unless you tick the box below)@endif.</p>
                    <div class="space-y-2 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" name="capital" value="none" @checked(old('capital', 'none') === 'none')> Open empty; I'll allocate by hand on the next page</label>
                        <label class="flex items-center gap-2"><input type="radio" name="capital" value="all" @checked(old('capital') === 'all')> Allocate <strong>all free capital</strong> ({{ Money::format($pool['total']) }}) from every eligible policy</label>
                        <label class="flex flex-wrap items-center gap-2"><input type="radio" name="capital" value="target" @checked(old('capital') === 'target')> Take a set amount, shared across eligible policies in proportion to their free capital:
                            <input name="target_amount" value="{{ old('target_amount') }}" placeholder="₦ amount" inputmode="decimal" class="w-40 rounded border border-gray-300 px-3 py-1.5"></label>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-2 border-t pt-3 text-sm">
                        <label class="flex items-center gap-2">Leave out policies maturing within
                            <input type="number" name="buffer_days" min="0" max="730" value="{{ old('buffer_days', $bufferDays) }}" class="w-20 rounded border border-gray-300 px-2 py-1"> days</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="include_maturing" value="1" @checked(old('include_maturing'))> Include them anyway</label>
                    </div>
                </fieldset>
                <x-btn>Propose cycle</x-btn>
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
