@php use App\Support\Money; @endphp
<x-layout title="Reconciliation">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Bank reconciliation</h1>
    <p class="mb-6 text-sm text-gray-600">Enter the closing balance from the bank statement. The system compares it with the ledger balance at the end of that day and records any difference. Records cannot be edited.</p>

    @can('reconciliation.manage')
        <x-card class="mb-6">
            <form method="POST" action="{{ route('admin.reconciliation.store') }}" class="grid gap-3 md:grid-cols-5 md:items-end">@csrf
                <label class="block text-sm font-bold text-gray-700">Account
                    <select name="account" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 font-normal">
                        @foreach($accounts as $a)<option value="{{ $a->code }}" @selected(old('account') === $a->code)>{{ $a->name }}</option>@endforeach
                    </select></label>
                <x-field name="as_of" label="Statement date" type="date" :value="now()->toDateString()" required />
                <x-field name="statement_balance" label="Statement balance (₦)" inputmode="decimal" required />
                <x-field name="note" label="Note" />
                <x-btn>Record</x-btn>
            </form>
        </x-card>
    @endcan

    <x-card>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr>
            <th class="py-2">Date</th><th>Account</th><th class="text-right">Bank statement</th><th class="text-right">Ledger</th><th class="text-right">Difference</th><th>By</th><th>Note</th></tr></thead><tbody>
        @forelse($records as $r)
            <tr class="border-b last:border-0 {{ $r->isBalanced() ? '' : 'bg-red-50' }}">
                <td class="py-2">{{ $r->as_of->format('d M Y') }}</td><td>{{ $r->account->name }}</td>
                <td class="text-right font-mono">{{ Money::format($r->statement_balance) }}</td>
                <td class="text-right font-mono">{{ Money::format($r->ledger_balance) }}</td>
                <td class="text-right font-mono font-bold {{ $r->isBalanced() ? 'text-green-700' : 'text-red-700' }}">{{ $r->isBalanced() ? '✔ balanced' : ($r->difference > 0 ? '+' : '−').Money::format(abs($r->difference)) }}</td>
                <td>{{ $r->reconciler?->name }}</td><td>{{ $r->note }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-6 text-center text-gray-500">No reconciliations recorded yet.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>
</x-layout>
