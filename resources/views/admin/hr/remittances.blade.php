@php use App\Support\Money; @endphp
<x-layout title="Remittances">
    <h1 class="mb-1 text-2xl font-black text-brand-700">PAYE, pension and NHF remittances</h1>
    <p class="mb-4 text-sm text-gray-600">Created when a payroll is paid. Download the schedule, pay the authority from the bank, then record the bank reference here. Accounts pays (not the person who prepared that payroll) and the payment is blocked if Main Operations cannot cover it.</p>

    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">To pay</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Due</th><th>What</th><th>Pay to</th><th>Month</th><th class="text-right">Amount</th><th></th></tr></thead><tbody>
        @forelse($due as $r)
            <tr class="border-b align-top last:border-0">
                <td class="py-2 {{ $r->isOverdue() ? 'font-bold text-red-700' : '' }}">{{ $r->due_date->format('d M Y') }}@if($r->isOverdue())<br><span class="text-xs">OVERDUE</span>@endif</td>
                <td>{{ $r->label() }}</td>
                <td>{{ $r->payee }}@if($r->administrator?->account_number)<br><span class="text-xs text-gray-500">{{ $r->administrator->bank_name }} {{ $r->administrator->account_number }} ({{ $r->administrator->account_name }})</span>@endif</td>
                <td>{{ $r->run->label() }}</td>
                <td class="text-right font-mono">{{ Money::format($r->amount) }}</td>
                <td class="space-y-2">
                    @canany(['payroll.pay', 'payroll.manage'])<a class="block text-brand-700 underline" href="{{ route('admin.hr.remittances.schedule', $r) }}">Schedule (CSV)</a>@endcanany
                    @can('payroll.pay')<form method="POST" action="{{ route('admin.hr.remittances.pay', $r) }}" class="flex gap-1">@csrf
                        <input name="reference" placeholder="Bank reference" required class="w-32 rounded border px-2 py-1 text-xs"><x-btn>Mark paid</x-btn></form>@endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-6 text-center text-gray-500">Nothing to remit. Remittances appear here when a payroll is paid.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>

    <x-card>
        <h2 class="mb-3 font-black text-brand-700">Paid</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Paid</th><th>What</th><th>Paid to</th><th>Month</th><th>Reference</th><th class="text-right">Amount</th></tr></thead><tbody>
        @forelse($done as $r)
            <tr class="border-b last:border-0"><td class="py-2">{{ $r->remitted_at->format('d M Y') }}<br><span class="text-xs text-gray-500">{{ $r->remitter?->name }}</span></td><td>{{ $r->label() }}</td><td>{{ $r->payee }}</td><td>{{ $r->run->label() }}</td><td class="font-mono text-xs">{{ $r->reference }}</td><td class="text-right font-mono">{{ Money::format($r->amount) }}</td></tr>
        @empty
            <tr><td colspan="6" class="py-4 text-center text-gray-500">Nothing remitted yet.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>
</x-layout>
