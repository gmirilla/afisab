@php use App\Support\Money; use App\Enums\PaymentStatus; @endphp
<x-layout title="Payments">
    <h1 class="mb-4 text-2xl font-black text-brand-700">Bank-transfer payments</h1>
    <x-card>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Reference</th><th>Investor</th><th>Amount</th><th>Status</th><th>Receipt</th><th></th></tr></thead><tbody>
        @forelse($payments as $p)
            <tr class="border-b align-top last:border-0">
                <td class="py-2 font-mono">{{ $p->reference }}<br><span class="text-xs text-gray-500">{{ $p->investment->reference }} · profit {{ strtolower($p->investment->profit_frequency->label()) }}</span></td>
                <td>{{ $p->investment->user->name }}</td><td>{{ Money::format($p->amount) }}</td><td>{{ $p->status->label() }}</td>
                <td>@if($p->proof_path)<a class="text-brand-700 underline" target="_blank" href="{{ route('admin.payments.proof', $p) }}">View</a>@else — @endif</td>
                <td class="space-y-2">
                    @if($p->status === PaymentStatus::AwaitingConfirmation)
                        <form method="POST" action="{{ route('admin.payments.confirm', $p) }}">@csrf<x-btn>Confirm received</x-btn></form>
                    @endif
                    <form method="POST" action="{{ route('admin.payments.reject', $p) }}" class="flex gap-1">@csrf
                        <input name="note" placeholder="Reason" required class="w-32 rounded border px-2 py-1 text-xs"><x-btn variant="danger">Reject</x-btn></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-6 text-center text-gray-500">Nothing awaiting confirmation.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>
</x-layout>
