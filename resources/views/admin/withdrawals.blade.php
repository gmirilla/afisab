@php use App\Support\Money; use App\Models\WithdrawalRequest; @endphp
<x-layout title="Early withdrawals">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Early withdrawals</h1>
    <p class="mb-4 text-sm text-gray-600">Investors who have given notice that they want their capital back before the term ends (agreement clause 9.2). When the notice has run and none of the policy's capital is still in a trading cycle, the policy matures early and goes through the normal <a class="underline" href="{{ route('admin.payouts') }}">capital payout</a>: prepared by Accounts, approved by a Director, paid by Accounts, with the cash check. A daily job does the maturing automatically at 06:30; the button below does it now.</p>
    @if($errors->has('withdrawal'))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('withdrawal') }}</p>@endif

    <x-card class="mb-6 overflow-x-auto">
        <h2 class="mb-3 font-black text-brand-700">Notices running</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Investor / policy</th><th class="text-right">Capital</th><th>Notice given</th><th>Notice ends</th><th>Waiting for</th><th></th></tr></thead><tbody>
        @forelse($open as $row)
            @php($r = $row['request'])
            <tr class="border-b align-top last:border-0">
                <td class="py-2"><a class="font-bold text-brand-700 underline" href="{{ route('admin.investors.show', $r->investment->user) }}">{{ $r->investment->user->name }}</a><br><span class="font-mono text-xs text-gray-500">{{ $r->investment->reference }}</span>@if($r->reason)<br><span class="text-xs text-gray-500">“{{ $r->reason }}”</span>@endif</td>
                <td class="text-right font-mono">{{ Money::format($r->investment->amount) }}</td>
                <td>{{ $r->requested_at->format('d M Y') }}</td>
                <td class="{{ $r->noticeElapsed() ? 'font-bold text-emerald-700' : '' }}">{{ $r->notice_ends_on->format('d M Y') }}</td>
                <td class="text-xs">@forelse($row['blockers'] as $b)<p class="mb-1 text-amber-800">{{ $b }}</p>@empty<span class="font-bold text-emerald-700">Nothing: ready to mature</span>@endforelse</td>
                <td class="space-y-2">
                    @can('payouts.prepare')
                        @if($row['blockers'] === [])<form method="POST" action="{{ route('admin.withdrawals.process', $r) }}" data-confirm="Mature this investment early now?">@csrf<x-btn>Mature now</x-btn></form>@endif
                        <form method="POST" action="{{ route('admin.withdrawals.cancel', $r) }}" class="flex gap-1">@csrf
                            <input name="note" placeholder="Reason to cancel" required minlength="5" class="w-32 rounded border px-2 py-1 text-xs"><x-btn variant="ghost">Cancel</x-btn></form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-6 text-center text-gray-500">No notices running.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>

    <x-card class="overflow-x-auto">
        <h2 class="mb-3 font-black text-brand-700">History</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Investor / policy</th><th class="text-right">Capital</th><th>Notice given</th><th>Outcome</th></tr></thead><tbody>
        @forelse($others as $r)
            <tr class="border-b last:border-0">
                <td class="py-2">{{ $r->investment->user->name }}<br><span class="font-mono text-xs text-gray-500">{{ $r->investment->reference }}</span></td>
                <td class="text-right font-mono">{{ Money::format($r->investment->amount) }}</td>
                <td>{{ $r->requested_at->format('d M Y') }}</td>
                <td>{{ $r->label() }}<br><span class="text-xs text-gray-500">
                    @if($r->status === WithdrawalRequest::CANCELLED){{ $r->cancelled_at?->format('d M Y') }} · {{ $r->cancel_note }}
                    @elseif($r->matured_at)Matured early {{ $r->matured_at->format('d M Y') }} (was due {{ $r->original_maturity_date?->format('d M Y') }})@if($r->completed_at) · capital paid {{ $r->completed_at->format('d M Y') }}@endif @endif</span></td>
            </tr>
        @empty
            <tr><td colspan="4" class="py-4 text-center text-gray-500">Nothing yet.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>
</x-layout>
