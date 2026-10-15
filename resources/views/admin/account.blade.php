@php use App\Support\Money; use App\Models\JournalLine; @endphp
<x-layout :title="$account->name">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-black text-brand-700">{{ $account->name }}</h1>
        <a class="text-sm underline" href="{{ route('admin.funds') }}">← Funds</a>
    </div>
    <p class="mb-4 text-sm text-gray-600">{{ $account->description }}</p>

    <x-card class="mb-6">
        <p class="text-sm text-gray-500">Current balance</p><p class="text-2xl font-black text-brand-700">{{ Money::format($balance) }}</p>
        <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
            <x-field name="from" label="From" type="date" :value="$filters['from'] ?? null" />
            <x-field name="to" label="To" type="date" :value="$filters['to'] ?? null" />
            <x-field name="policy" label="Policy reference" :value="$filters['policy'] ?? null" placeholder="AFI-…" />
            <x-btn>Filter</x-btn>
            <a class="py-2 text-sm underline" href="{{ route('admin.ledger.account', $account->code) }}">Clear</a>
        </form>
        @if(!empty($filters['policy']) && !$policy)<p class="mt-2 text-sm text-red-700">No policy with that reference.</p>@endif
    </x-card>

    <x-card>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr>
            <th class="py-2">Date</th><th>Entry</th><th>Description</th><th>Policy</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead><tbody>
        @forelse($lines as $l)
            <tr class="border-b last:border-0">
                <td class="py-2">{{ $l->entry->posted_at->format('d M Y H:i') }}</td>
                <td class="font-mono text-xs"><a class="underline" href="{{ route('admin.ledger.entry', $l->entry) }}">{{ $l->entry->reference }}</a></td>
                <td>{{ $l->entry->description }}</td>
                <td class="font-mono text-xs">{{ $l->subject_type === 'investment' ? $l->subject?->reference : '' }}</td>
                <td class="text-right font-mono">{{ $l->direction === JournalLine::DEBIT ? Money::format($l->amount) : '' }}</td>
                <td class="text-right font-mono">{{ $l->direction === JournalLine::CREDIT ? Money::format($l->amount) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-6 text-center text-gray-500">No ledger lines match.</td></tr>
        @endforelse
        </tbody></table>
        <div class="mt-4">{{ $lines->links() }}</div>
    </x-card>
</x-layout>
