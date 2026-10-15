@php use App\Models\LedgerReversal; @endphp
<x-layout title="Ledger reversals">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Ledger reversals</h1>
    <p class="mb-4 text-sm text-gray-600">The ledger is never edited. A mistake is corrected by an equal and opposite entry: Accounts requests it from the entry's page (open an account in Funds, then click the entry reference), and a Director approves it (a different person).</p>
    @if($errors->has('reversal'))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('reversal') }}</p>@endif

    <x-card>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Entry</th><th>Reason</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($reversals as $r)
            <tr class="border-b align-top last:border-0">
                <td class="py-2"><a class="font-mono text-xs underline" href="{{ route('admin.ledger.entry', $r->entry) }}">{{ $r->entry->reference }}</a><br><span class="text-xs text-gray-500">{{ $r->entry->description }}</span></td>
                <td>{{ $r->reason }}</td>
                <td>{{ ucfirst($r->status) }}<br><span class="text-xs text-gray-500">
                    requested by {{ $r->requester?->name }} {{ $r->created_at->format('d M Y') }}
                    @if($r->decider) · {{ $r->status === LedgerReversal::REJECTED ? 'rejected' : 'approved' }} by {{ $r->decider->name }} @endif
                    @if($r->reversalEntry) · <a class="underline" href="{{ route('admin.ledger.entry', $r->reversalEntry) }}">{{ $r->reversalEntry->reference }}</a> @endif
                    @if($r->decision_note) · {{ $r->decision_note }} @endif</span></td>
                <td class="space-y-2">
                    @if($r->status === LedgerReversal::REQUESTED)
                        @can('reversals.approve')
                            <form method="POST" action="{{ route('admin.reversals.approve', $r) }}">@csrf<x-btn>Approve and post</x-btn></form>
                            <form method="POST" action="{{ route('admin.reversals.reject', $r) }}" class="flex gap-1">@csrf
                                <input name="note" placeholder="Reason" required class="w-28 rounded border px-2 py-1 text-xs"><x-btn variant="danger">Reject</x-btn></form>
                        @endcan
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="py-6 text-center text-gray-500">No reversals yet.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>
</x-layout>
