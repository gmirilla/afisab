@php use App\Support\Money; use App\Models\CapitalPayout; @endphp
<x-layout title="Capital payouts">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Capital payouts</h1>
    <p class="mb-6 text-sm text-gray-600">Returning investors' principal at maturity. Accounts prepares, a Director approves (a different person), then Accounts pays and records the bank reference.
        Main Operations currently holds <strong>{{ Money::format($overview['main_ops']) }}</strong>.</p>

    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Matured, awaiting a payout</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Policy</th><th>Investor</th><th>Matured</th><th class="text-right">Principal</th><th></th></tr></thead><tbody>
        @forelse($eligible as $i)
            <tr class="border-b last:border-0">
                <td class="py-2 font-mono">{{ $i->reference }}</td><td>{{ $i->user->name }}</td><td>{{ $i->maturity_date->format('d M Y') }}</td>
                <td class="text-right font-mono">{{ Money::format($i->amount) }}</td>
                <td class="text-right">@can('payouts.prepare')<form method="POST" action="{{ route('admin.payouts.prepare', $i) }}">@csrf<x-btn>Prepare payout</x-btn></form>@endcan</td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-4 text-center text-gray-500">Nothing waiting. Policies appear here the day after they mature.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>

    <x-card>
        <h2 class="mb-3 font-black text-brand-700">Payouts</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Policy</th><th>Investor / bank</th><th class="text-right">Amount</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($payouts as $p)
            @php($profile = $p->investment->user->investorProfile)
            <tr class="border-b align-top last:border-0">
                <td class="py-2 font-mono">{{ $p->investment->reference }}</td>
                <td>{{ $p->investment->user->name }}<br><span class="text-xs text-gray-500">{{ $profile?->bank_name }} {{ $profile?->account_number }} ({{ $profile?->account_name }})</span></td>
                <td class="text-right font-mono">{{ Money::format($p->amount) }}</td>
                <td>{{ ucfirst($p->status) }}<br><span class="text-xs text-gray-500">
                    @if($p->preparer) prepared by {{ $p->preparer->name }} @endif
                    @if($p->approver && $p->status !== CapitalPayout::PREPARED) · {{ $p->status === CapitalPayout::REJECTED ? 'rejected' : 'approved' }} by {{ $p->approver->name }} @endif
                    @if($p->payment_reference) · ref {{ $p->payment_reference }} @endif
                    @if($p->note) · {{ $p->note }} @endif</span></td>
                <td class="space-y-2">
                    @if($p->status === CapitalPayout::PREPARED)
                        @can('payouts.approve')
                            <form method="POST" action="{{ route('admin.payouts.approve', $p) }}">@csrf<x-btn>Approve</x-btn></form>
                            <form method="POST" action="{{ route('admin.payouts.reject', $p) }}" class="flex gap-1">@csrf
                                <input name="note" placeholder="Reason" required class="w-28 rounded border px-2 py-1 text-xs"><x-btn variant="danger">Reject</x-btn></form>
                        @endcan
                    @elseif($p->status === CapitalPayout::APPROVED)
                        @can('payouts.prepare')
                            <form method="POST" action="{{ route('admin.payouts.pay', $p) }}" class="flex gap-1">@csrf
                                <input name="reference" placeholder="Bank reference" required class="w-32 rounded border px-2 py-1 text-xs"><x-btn>Mark paid</x-btn></form>
                        @endcan
                        @can('payouts.approve')
                            <form method="POST" action="{{ route('admin.payouts.reject', $p) }}" class="flex gap-1">@csrf
                                <input name="note" placeholder="Reason" required class="w-28 rounded border px-2 py-1 text-xs"><x-btn variant="danger">Reject</x-btn></form>
                        @endcan
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-6 text-center text-gray-500">No payouts yet.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>
</x-layout>
