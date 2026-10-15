@php use App\Support\Money; @endphp
<x-layout title="Trial balance">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-black text-brand-700">Trial balance</h1>
        <div class="flex gap-3 text-sm"><a class="underline" href="{{ route('admin.reports.index') }}">← Reports</a>
            <a class="rounded bg-brand-700 px-3 py-1 font-bold text-white" href="{{ route('admin.reports.trial-balance', ['as_of' => $asOf->toDateString(), 'format' => 'csv']) }}">Download CSV</a></div>
    </div>
    <form method="GET" class="mb-4 flex items-end gap-3"><x-field name="as_of" label="As at" type="date" :value="$asOf->toDateString()" /><x-btn>Show</x-btn></form>
    <x-card>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Code</th><th>Account</th><th>Type</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead><tbody>
            @foreach($report['rows'] as $r)
                <tr class="border-b last:border-0"><td class="py-2 font-mono text-xs">{{ $r['code'] }}</td><td>{{ $r['name'] }}</td><td>{{ ucfirst($r['type']) }}</td>
                    <td class="text-right font-mono">{{ $r['debit'] ? Money::format($r['debit']) : '' }}</td><td class="text-right font-mono">{{ $r['credit'] ? Money::format($r['credit']) : '' }}</td></tr>
            @endforeach
        </tbody><tfoot class="border-t font-bold"><tr><td colspan="3" class="py-2">Total</td>
            <td class="text-right font-mono">{{ Money::format($report['total_debit']) }}</td><td class="text-right font-mono">{{ Money::format($report['total_credit']) }}</td></tr></tfoot></table>
        <p class="mt-3 text-sm font-bold {{ $report['balanced'] ? 'text-green-700' : 'text-red-700' }}">{{ $report['balanced'] ? '✔ Debits equal credits: the ledger balances.' : '✘ Debits and credits differ. Investigate immediately.' }}</p>
    </x-card>
</x-layout>
