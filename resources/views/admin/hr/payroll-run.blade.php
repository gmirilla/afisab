@php use App\Support\Money; use App\Models\PayrollRun; @endphp
<x-layout :title="'Payroll '.$run->period">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-black text-brand-700">{{ $run->label() }} payroll <span class="ml-2 rounded bg-brand-50 px-2 py-1 text-sm">{{ ucfirst($run->status) }}</span></h1>
        <a class="text-sm underline" href="{{ route('admin.hr.payroll') }}">← Payroll</a>
    </div>
    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <x-card><p class="text-xs text-gray-500">Gross</p><p class="text-xl font-black">{{ Money::format($run->total_gross) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">Deductions withheld</p><p class="text-xl font-black">{{ Money::format($run->total_deductions) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">Net to pay staff</p><p class="text-xl font-black text-brand-700">{{ Money::format($run->total_net) }}</p></x-card>
    </div>
    <x-card class="mb-6 text-sm">
        Prepared by {{ $run->preparer?->name }} @if($run->approved_at) · approved {{ $run->approved_at->format('d M Y H:i') }} by {{ $run->approver?->name }} @endif
        @if($run->paid_at) · paid {{ $run->paid_at->format('d M Y H:i') }} (ref {{ $run->payment_reference }}) @endif
        <div class="mt-3 flex flex-wrap items-end gap-3">
            @if($run->status === PayrollRun::DRAFT) @can('payroll.approve')<form method="POST" action="{{ route('admin.hr.payroll.approve', $run) }}" data-confirm="Approve this payroll?">@csrf<x-btn>Approve payroll</x-btn></form>@endcan @endif
            @if($run->status !== PayrollRun::DRAFT) @canany(['payroll.pay', 'payroll.manage'])<a class="rounded border border-brand-700 px-4 py-2 text-sm font-bold text-brand-700" href="{{ route('admin.hr.payroll.export', $run) }}">Download bank CSV</a>@endcanany @endif
            @if($run->status === PayrollRun::APPROVED) @can('payroll.pay')<form method="POST" action="{{ route('admin.hr.payroll.pay', $run) }}" class="flex items-end gap-2">@csrf<x-field name="reference" label="Bank batch reference" required /><x-btn>Mark paid</x-btn></form>@endcan @endif
        </div>
    </x-card>
    <x-card><table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Employee</th><th class="text-right">Basic</th><th class="text-right">Allowances</th><th class="text-right">Commission</th><th class="text-right">Deductions</th><th class="text-right">Net</th></tr></thead><tbody>
        @foreach($lines as $l)
            <tr class="border-b last:border-0"><td class="py-2">{{ $l->employee->name }}</td><td class="text-right font-mono">{{ Money::format($l->basic) }}</td><td class="text-right font-mono">{{ Money::format($l->allowances) }}</td>
                <td class="text-right font-mono">{{ Money::format($l->commission) }}</td><td class="text-right font-mono">{{ Money::format($l->deductions) }}</td><td class="text-right font-mono font-bold">{{ Money::format($l->net) }}</td></tr>
        @endforeach
    </tbody></table></x-card>
</x-layout>
