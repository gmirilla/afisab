@php use App\Support\Money; use App\Models\PayrollRun; @endphp
<x-layout title="Payroll">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Payroll</h1>
    <p class="mb-6 text-sm text-gray-600">HR prepares the month, a Director approves (a different person), Accounts pays.
        @if($ruleSet)PAYE, pension and NHF are <strong>calculated automatically</strong> from <a class="underline" href="{{ route('admin.statutory') }}">{{ $ruleSet->name }}</a> (PAYE by the year-to-date method, so commission that varies month to month evens out). Enter only loans and other non-statutory items as deductions.
        @else Tax, pension and other deductions are entered by hand as adjustments; no <a class="underline" href="{{ route('admin.statutory') }}">statutory rate set</a> is active for this month.@endif
        Earlier months of the year must be approved before the next is prepared.</p>

    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Adjustments for {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $period)->format('F Y') }}</h2>
        <form method="GET" class="mb-3 flex items-end gap-3"><x-field name="period" label="Month" type="month" :value="$period" /><x-btn variant="ghost">Show</x-btn></form>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Employee</th><th>Type</th><th>Description</th><th class="text-right">Amount</th><th></th></tr></thead><tbody>
            @forelse($adjustments as $a)
                <tr class="border-b last:border-0"><td class="py-2">{{ $a->employee->name }}</td><td>{{ ucfirst($a->type) }}</td><td>{{ $a->description }}</td><td class="text-right font-mono">{{ Money::format($a->amount) }}</td>
                    <td class="text-right">@can('payroll.manage')<form method="POST" action="{{ route('admin.hr.payroll.adjustments.destroy', $a) }}">@csrf @method('DELETE')<x-btn variant="ghost">Remove</x-btn></form>@endcan</td></tr>
            @empty <tr><td colspan="5" class="py-3 text-center text-gray-500">No adjustments this month.</td></tr> @endforelse
        </tbody></table>
        @can('payroll.manage')
            <form method="POST" action="{{ route('admin.hr.payroll.adjustments.store') }}" class="mt-4 flex flex-wrap items-end gap-3 border-t pt-4">@csrf
                <input type="hidden" name="period" value="{{ $period }}">
                <label class="block text-sm font-bold text-gray-700">Employee<select name="employee_id" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal">@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select></label>
                <label class="block text-sm font-bold text-gray-700">Type<select name="type" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal"><option value="allowance">Allowance</option><option value="commission">Commission</option><option value="deduction">Deduction</option></select></label>
                <x-field name="description" label="Description" required /><x-field name="amount" label="Amount (₦)" inputmode="decimal" required />
                <x-btn>Add</x-btn>
            </form>
            <form method="POST" action="{{ route('admin.hr.payroll.prepare') }}" class="mt-4 border-t pt-4">@csrf<input type="hidden" name="period" value="{{ $period }}"><x-btn>Prepare / rebuild payroll for this month</x-btn></form>
        @endcan
    </x-card>

    <x-card>
        <h2 class="mb-3 font-black text-brand-700">Payroll runs</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Month</th><th>Status</th><th class="text-right">Gross</th><th class="text-right">Deductions</th><th class="text-right">Net</th><th></th></tr></thead><tbody>
            @forelse($runs as $r)
                <tr class="border-b last:border-0"><td class="py-2 font-bold">{{ $r->label() }}</td><td>{{ ucfirst($r->status) }}</td><td class="text-right font-mono">{{ Money::format($r->total_gross) }}</td>
                    <td class="text-right font-mono">{{ Money::format($r->total_deductions) }}</td><td class="text-right font-mono">{{ Money::format($r->total_net) }}</td>
                    <td><a class="text-brand-700 underline" href="{{ route('admin.hr.payroll.show', $r) }}">Open</a></td></tr>
            @empty <tr><td colspan="6" class="py-4 text-center text-gray-500">No payroll runs yet.</td></tr> @endforelse
        </tbody></table>
    </x-card>
</x-layout>
