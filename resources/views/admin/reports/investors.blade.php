@php use App\Support\Money; @endphp
<x-layout title="Investors report">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-black text-brand-700">Investors and policies</h1>
        <div class="flex gap-3 text-sm"><a class="underline" href="{{ route('admin.reports.index') }}">← Reports</a>
            <a class="rounded bg-brand-700 px-3 py-1 font-bold text-white" href="{{ route('admin.reports.investors', array_filter(['status' => $status, 'format' => 'csv'])) }}">Download CSV</a></div>
    </div>
    <form method="GET" class="mb-4 flex items-end gap-3">
        <label class="block text-sm font-bold text-gray-700">Status
            <select name="status" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal" data-autosubmit>
                <option value="">All</option>
                @foreach(['pending_payment','active','matured','paid_out','cancelled'] as $s)<option value="{{ $s }}" @selected($status === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach
            </select></label>
    </form>
    <x-card><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr>
        <th class="py-2">Policy</th><th>Investor</th><th>Plan</th><th>Profit paid</th><th class="text-right">Amount</th><th>Status</th><th>Start</th><th>Maturity</th><th class="text-right">Profit paid</th><th class="text-right">Profit payable</th></tr></thead><tbody>
        @forelse($rows as $r)
            <tr class="border-b last:border-0"><td class="py-2 font-mono">{{ $r['reference'] }}</td><td>{{ $r['investor'] }}</td><td>{{ $r['plan'] }}</td><td>{{ $r['frequency'] }}</td>
                <td class="text-right font-mono">{{ Money::format($r['amount']) }}</td><td>{{ $r['status'] }}</td><td>{{ $r['start'] ?? '—' }}</td><td>{{ $r['maturity'] ?? '—' }}</td>
                <td class="text-right font-mono">{{ Money::format($r['profit_paid']) }}</td><td class="text-right font-mono">{{ Money::format($r['profit_payable']) }}</td></tr>
        @empty <tr><td colspan="10" class="py-6 text-center text-gray-500">No policies.</td></tr> @endforelse
        </tbody>
        @if($rows->isNotEmpty())<tfoot class="border-t font-bold"><tr><td colspan="4" class="py-2">Total</td><td class="text-right font-mono">{{ Money::format($rows->sum('amount')) }}</td><td colspan="3"></td>
            <td class="text-right font-mono">{{ Money::format($rows->sum('profit_paid')) }}</td><td class="text-right font-mono">{{ Money::format($rows->sum('profit_payable')) }}</td></tr></tfoot>@endif
    </table></div></x-card>
</x-layout>
