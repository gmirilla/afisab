@php use App\Support\Money; use App\Models\JournalLine; use App\Models\LedgerReversal; @endphp
<x-layout :title="'Entry '.$entry->reference">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-black text-brand-700">Journal entry <span class="font-mono text-lg">{{ $entry->reference }}</span></h1>
        <a class="text-sm underline" href="{{ route('admin.reversals') }}">Reversals →</a>
    </div>
    <p class="mb-4 text-sm text-gray-600">{{ $entry->description }}<br>
        Posted {{ $entry->posted_at->format('d M Y H:i') }}@if($entry->poster) by {{ $entry->poster->name }}@endif.
        @if($reverses) This entry reverses <a class="underline" href="{{ route('admin.ledger.entry', $reverses) }}">{{ $reverses->reference }}</a>.@endif</p>

    <x-card class="mb-6">
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Account</th><th>Policy</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead><tbody>
        @foreach($entry->lines as $l)
            <tr class="border-b last:border-0">
                <td class="py-2"><a class="underline" href="{{ route('admin.ledger.account', $l->account->code) }}">{{ $l->account->name }}</a></td>
                <td class="font-mono text-xs">{{ $l->subject_type === 'investment' ? $l->subject?->reference : '' }}</td>
                <td class="text-right font-mono">{{ $l->direction === JournalLine::DEBIT ? Money::format($l->amount) : '' }}</td>
                <td class="text-right font-mono">{{ $l->direction === JournalLine::CREDIT ? Money::format($l->amount) : '' }}</td>
            </tr>
        @endforeach
        </tbody></table>
    </x-card>

    <x-card>
        <h2 class="mb-2 font-black text-brand-700">Correct this entry</h2>
        @if($isReversal)
            <p class="text-sm text-gray-600">This is a reversal. It cannot be reversed again; post a fresh, correct entry through the normal flow instead.</p>
        @elseif($reversal?->status === LedgerReversal::APPROVED)
            <p class="text-sm">Reversed by <a class="underline" href="{{ route('admin.ledger.entry', $reversal->reversalEntry) }}">{{ $reversal->reversalEntry->reference }}</a> on {{ $reversal->decided_at->format('d M Y') }}. Reason: {{ $reversal->reason }}.</p>
        @elseif($reversal?->status === LedgerReversal::REQUESTED)
            <p class="text-sm">A reversal was requested by {{ $reversal->requester?->name }} and is waiting for a Director. Reason: {{ $reversal->reason }}.</p>
        @else
            @can('reversals.request')
                @if($errors->has('reversal'))<p class="mb-2 text-sm text-red-700">{{ $errors->first('reversal') }}</p>@endif
                <p class="mb-3 text-sm text-gray-600">Posts an equal and opposite entry once a Director approves; the original is never changed. This corrects the <strong>ledger only</strong>: it does not undo the payment, payout or other record that created the entry.</p>
                <form method="POST" action="{{ route('admin.reversals.request', $entry) }}" class="flex flex-wrap items-end gap-3">@csrf
                    <x-field name="reason" label="Reason" required class="w-96" />
                    <x-btn variant="danger">Request reversal</x-btn>
                </form>
            @else
                <p class="text-sm text-gray-600">Only Accounts can request a reversal.</p>
            @endcan
        @endif
    </x-card>
</x-layout>
