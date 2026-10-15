@php use App\Support\Money; @endphp
<x-layout title="Profit and loss">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-black text-brand-700">Profit and loss</h1>
        <div class="flex gap-3 text-sm"><a class="underline" href="{{ route('admin.reports.index') }}">← Reports</a>
            <a class="rounded bg-brand-700 px-3 py-1 font-bold text-white" href="{{ route('admin.reports.profit-loss', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'format' => 'csv']) }}">Download CSV</a></div>
    </div>
    <form method="GET" class="mb-4 flex items-end gap-3"><x-field name="from" label="From" type="date" :value="$from->toDateString()" /><x-field name="to" label="To" type="date" :value="$to->toDateString()" /><x-btn>Show</x-btn></form>
    <x-card>
        <table class="w-full text-sm"><tbody>
            @foreach($report['income'] as $r)<tr class="border-b"><td class="py-2">{{ $r['name'] }}</td><td class="text-right font-mono">{{ Money::format($r['amount']) }}</td></tr>@endforeach
            <tr class="border-b font-bold"><td class="py-2">Total income</td><td class="text-right font-mono">{{ Money::format($report['total_income']) }}</td></tr>
            @foreach($report['expenses'] as $r)<tr class="border-b"><td class="py-2">{{ $r['name'] }}</td><td class="text-right font-mono">{{ Money::format($r['amount']) }}</td></tr>@endforeach
            <tr class="border-b font-bold"><td class="py-2">Total expenses</td><td class="text-right font-mono">{{ Money::format($report['total_expenses']) }}</td></tr>
            <tr class="text-lg font-black {{ $report['net'] < 0 ? 'text-red-700' : 'text-brand-700' }}"><td class="py-3">Net profit</td><td class="text-right font-mono">{{ Money::format($report['net']) }}</td></tr>
        </tbody></table>
        <p class="mt-3 text-xs text-gray-500">Income is shown after the investors' approved profit share has been deducted. Payroll and Takaful expenses appear when they are paid.</p>
    </x-card>
</x-layout>
