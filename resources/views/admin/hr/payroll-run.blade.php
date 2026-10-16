@php use App\Support\Money; use App\Models\PayrollRun; @endphp
<x-layout :title="'Payroll '.$run->period">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-black text-brand-700">{{ $run->label() }} payroll <span class="ml-2 rounded bg-brand-50 px-2 py-1 text-sm">{{ ucfirst($run->status) }}</span></h1>
        <a class="text-sm underline" href="{{ route('admin.hr.payroll') }}">← Payroll</a>
    </div>
    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <x-card><p class="text-xs text-gray-500">Gross</p><p class="text-xl font-black">{{ Money::format($run->total_gross) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">Deductions withheld</p><p class="text-xl font-black">{{ Money::format($run->total_deductions + $run->total_paye + $run->total_pension_employee + $run->total_nhf) }}</p>
            @if($run->hasStatutory())<p class="text-xs text-gray-500">PAYE {{ Money::format($run->total_paye) }} · pension {{ Money::format($run->total_pension_employee) }} · NHF {{ Money::format($run->total_nhf) }} · other {{ Money::format($run->total_deductions) }}</p>@endif</x-card>
        <x-card><p class="text-xs text-gray-500">Net to pay staff</p><p class="text-xl font-black text-brand-700">{{ Money::format($run->total_net) }}</p></x-card>
    </div>
    @if($run->hasStatutory())
        <p class="mb-4 text-sm text-gray-600">Calculated with <strong>{{ $run->ruleSet->name }}</strong> (effective {{ $run->ruleSet->effective_from->format('d M Y') }}). Employer pension on top of gross: <strong>{{ Money::format($run->total_pension_employer) }}</strong>. PAYE uses the year-to-date method.
            <a class="underline" href="{{ route('admin.statutory') }}">Rates</a>@if($run->status === PayrollRun::PAID) · <a class="underline" href="{{ route('admin.hr.remittances') }}">Remittances</a>@endif</p>
    @else
        <p class="mb-4 rounded bg-amber-50 p-3 text-sm text-amber-900">No statutory rates were active for this month: PAYE, pension and NHF were not calculated. Deductions shown were entered by hand.</p>
    @endif
    <x-card class="mb-6 text-sm">
        Prepared by {{ $run->preparer?->name }} @if($run->approved_at) · approved {{ $run->approved_at->format('d M Y H:i') }} by {{ $run->approver?->name }} @endif
        @if($run->paid_at) · paid {{ $run->paid_at->format('d M Y H:i') }} (ref {{ $run->payment_reference }}) @endif
        <div class="mt-3 flex flex-wrap items-end gap-3">
            @if($run->status === PayrollRun::DRAFT) @can('payroll.approve')<form method="POST" action="{{ route('admin.hr.payroll.approve', $run) }}" data-confirm="Approve this payroll?">@csrf<x-btn>Approve payroll</x-btn></form>@endcan @endif
            @if($run->status !== PayrollRun::DRAFT) @canany(['payroll.pay', 'payroll.manage'])<a class="rounded border border-brand-700 px-4 py-2 text-sm font-bold text-brand-700" href="{{ route('admin.hr.payroll.export', $run) }}">Download bank CSV</a>@endcanany @endif
            @if($run->status === PayrollRun::APPROVED) @can('payroll.pay')<form method="POST" action="{{ route('admin.hr.payroll.pay', $run) }}" class="flex items-end gap-2">@csrf<x-field name="reference" label="Bank batch reference" required /><x-btn>Mark paid</x-btn></form>@endcan @endif
        </div>
    </x-card>
    <x-card class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Employee</th><th class="text-right">Basic + housing + transport</th><th class="text-right">Allowances</th><th class="text-right">Commission</th>
        @if($run->hasStatutory())<th class="text-right">Pension (employee)</th><th class="text-right">NHF</th><th class="text-right">PAYE</th><th class="text-right">Employer pension</th>@endif
        <th class="text-right">Other deductions</th><th class="text-right">Net</th></tr></thead><tbody>
        @foreach($lines as $l)
            <tr class="border-b last:border-0"><td class="py-2">{{ $l->employee->name }}@if($run->hasStatutory())<br><span class="text-xs text-gray-500">{{ $l->administrator?->name ?? 'no administrator' }} · month {{ $l->tax_months }} of tax year</span>@endif</td>
                <td class="text-right font-mono">{{ Money::format($l->basic + $l->housing + $l->transport) }}</td><td class="text-right font-mono">{{ Money::format($l->allowances) }}</td>
                <td class="text-right font-mono">{{ Money::format($l->commission) }}</td>
                @if($run->hasStatutory())<td class="text-right font-mono">{{ Money::format($l->pension_employee) }}</td><td class="text-right font-mono">{{ Money::format($l->nhf) }}</td><td class="text-right font-mono">{{ Money::format($l->paye) }}</td><td class="text-right font-mono text-gray-500">{{ Money::format($l->pension_employer) }}</td>@endif
                <td class="text-right font-mono">{{ Money::format($l->deductions) }}</td><td class="text-right font-mono font-bold">{{ Money::format($l->net) }}</td></tr>
        @endforeach
    </tbody></table></x-card>
</x-layout>
