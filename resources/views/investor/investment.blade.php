@php use App\Support\Money; use App\Enums\InvestmentStatus; use App\Enums\PaymentStatus; use App\Enums\PaymentMethod; @endphp
<x-layout :title="$investment->reference">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-black text-brand-700">{{ $investment->plan->name }} <span class="font-mono text-base text-gray-500">{{ $investment->reference }}</span></h1>
        <span class="rounded bg-brand-50 px-3 py-1 text-sm font-bold text-brand-700">{{ $investment->status->label() }}</span>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <x-card><p class="text-sm text-gray-500">Amount</p><p class="text-xl font-black">{{ Money::format($investment->amount) }}</p></x-card>
        <x-card><p class="text-sm text-gray-500">Duration</p><p class="text-xl font-black">{{ $investment->duration_months }} months</p></x-card>
        <x-card><p class="text-sm text-gray-500">Start</p><p class="text-xl font-black">{{ $investment->start_date?->format('d M Y') ?? '—' }}</p></x-card>
        <x-card><p class="text-sm text-gray-500">Maturity</p><p class="text-xl font-black">{{ $investment->maturity_date?->format('d M Y') ?? '—' }}</p></x-card>
    </div>

    @if($investment->hasCertificate())
        <x-card class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-gray-500">Investment certificate</p>
                <p class="font-mono text-lg font-black text-brand-700">{{ $investment->certificate_number }}</p>
                <p class="text-xs text-gray-500">Verification code: <span class="font-mono">{{ $investment->certificate_token }}</span> ·
                    <a class="underline" href="{{ route('verify.show', $investment->certificate_token) }}">public verification page</a></p>
            </div>
            <div class="flex gap-2">
                <a class="rounded bg-brand-700 px-4 py-2 text-sm font-bold text-white" href="{{ route('documents.certificate', $investment) }}">Download certificate (PDF)</a>
                <a class="rounded border border-brand-700 px-4 py-2 text-sm font-bold text-brand-700" href="{{ route('documents.statement', $investment) }}">Statement (PDF)</a>
            </div>
        </x-card>

        <div class="mb-6 grid gap-4 md:grid-cols-4">
            <x-card><p class="text-sm text-gray-500">Principal</p><p class="text-xl font-black">{{ Money::format($position['principal']) }}</p></x-card>
            <x-card><p class="text-sm text-gray-500">Profit received</p><p class="text-xl font-black">{{ Money::format($position['paid']) }}</p></x-card>
            <x-card><p class="text-sm text-gray-500">Profit approved, awaiting payment</p><p class="text-xl font-black">{{ Money::format($position['payable']) }}</p></x-card>
            <x-card><p class="text-sm text-gray-500">Current value</p><p class="text-xl font-black text-brand-700">{{ Money::format($position['current_value']) }}</p></x-card>
        </div>
        <p class="mb-6 text-sm text-gray-600">Next profit calculation: <strong>{{ $next['cutoff']->format('d F Y') }}</strong> · payment by <strong>{{ $next['pay_by']->format('d F Y') }}</strong>.</p>

        @if($distributions->isNotEmpty())
            <x-card class="mb-6">
                <h2 class="mb-3 font-black text-brand-700">Monthly profit shares</h2>
                <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Month</th><th>Your share</th><th>Status</th><th>Reference</th></tr></thead><tbody>
                    @foreach($distributions as $d)
                        <tr class="border-b last:border-0"><td class="py-2">{{ $d->run->label() }}</td><td>{{ Money::format($d->investor_share) }}</td>
                            <td>{{ $d->status === 'paid' ? 'Paid '.$d->paid_at->format('d M Y') : 'Approved, payment due by '.$d->run->pay_by->format('d M') }}</td>
                            <td class="font-mono">{{ $d->payment_reference ?? '—' }}</td></tr>
                    @endforeach
                </tbody></table>
            </x-card>
        @endif

        @if($investment->status === InvestmentStatus::Active)
            <x-card class="mb-6">
                <div class="h-3 overflow-hidden rounded bg-gray-200"><div class="h-3 bg-brand-600" style="width: {{ $investment->progressPercent() }}%"></div></div>
                <p class="mt-1 text-sm text-gray-600">{{ $investment->progressPercent() }}% of the term elapsed · {{ $investment->daysToMaturity() }} days to maturity</p>
            </x-card>
        @endif

        <x-card class="mb-6">
            <h2 class="mb-3 font-black text-brand-700">Trading performance</h2>
            <p class="mb-3 text-sm text-gray-600">Your funds are deployed into commodity trading cycles. Each month, on the 27th, the month's net profit is calculated and your share is approved and paid by month end. Returns are not guaranteed.</p>
            <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Cycle</th><th>Closed</th><th>Your capital in cycle</th><th>Included in</th></tr></thead><tbody>
                @forelse($entries as $e)
                    <tr class="border-b last:border-0">
                        <td class="py-2 font-mono">{{ $e->cycle_reference }}</td><td>{{ $e->recorded_at->format('d M Y') }}</td>
                        <td>{{ Money::format($e->capital_allocated) }}</td>
                        <td>@if($e->run) {{ $e->run->label() }} distribution @else <span class="text-amber-700">Next monthly calculation</span> @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-gray-500">No trading cycle has closed for this investment yet.</td></tr>
                @endforelse
            </tbody></table>
        </x-card>
    @endif

    @if($investment->status === InvestmentStatus::PendingPayment)
        <div class="grid gap-4 md:grid-cols-2">
            <x-card>
                <h2 class="font-black text-brand-700">Pay online (card / bank)</h2>
                <p class="my-2 text-sm text-gray-600">Secure checkout by Paystack.</p>
                <form method="POST" action="{{ route('investor.payments.paystack', $investment) }}">@csrf<x-btn>Pay {{ Money::format($investment->amount) }}</x-btn></form>
            </x-card>
            <x-card>
                <h2 class="font-black text-brand-700">Pay by bank transfer</h2>
                <p class="my-2 text-sm text-gray-600">Transfer exactly <strong>{{ Money::format($investment->amount) }}</strong> to:</p>
                @forelse(app(\App\Services\Payments\BankAccounts::class)->active() as $bank)
                    <p class="mb-2 text-sm text-gray-600">{{ $bank['name'] }} · {{ $bank['account_number'] }}<br>{{ $bank['account_name'] }}@if($bank['note'])<br><span class="text-xs italic">{{ $bank['note'] }}</span>@endif</p>
                @empty
                    <p class="mb-2 text-sm text-red-700">Bank transfer details are unavailable right now. Please pay online or contact us.</p>
                @endforelse
                <p class="mb-3 text-sm text-gray-600">Narration: <span class="font-mono">{{ $investment->reference }}</span></p>
                <form method="POST" action="{{ route('investor.payments.transfer', $investment) }}">@csrf<x-btn variant="ghost">I have made the transfer</x-btn></form>

                @foreach($investment->payments->where('method', PaymentMethod::Transfer)->whereIn('status', [PaymentStatus::Pending, PaymentStatus::AwaitingConfirmation]) as $p)
                    <form method="POST" action="{{ route('investor.payments.proof', $p) }}" enctype="multipart/form-data" class="mt-4 space-y-3 border-t pt-4">@csrf
                        <x-field name="proof" label="Upload receipt (JPG, PNG or PDF, max 4MB)" type="file" required />
                        <x-btn>Submit receipt</x-btn>
                        @if($p->status === PaymentStatus::AwaitingConfirmation)<p class="text-sm text-amber-700">Receipt submitted; awaiting confirmation by Accounts.</p>@endif
                    </form>
                @endforeach
            </x-card>
        </div>
        <form method="POST" action="{{ route('investor.investments.cancel', $investment) }}" class="mt-4">@csrf @method('DELETE')<x-btn variant="ghost">Cancel this subscription</x-btn></form>
    @endif

    <x-card class="mt-6">
        <h2 class="mb-3 font-black text-brand-700">Payments</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Reference</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead><tbody>
            @forelse($investment->payments as $p)
                <tr class="border-b last:border-0"><td class="py-2 font-mono">{{ $p->reference }}</td><td>{{ ucfirst($p->method->value) }}</td><td>{{ Money::format($p->amount) }}</td><td>{{ $p->status->label() }}</td></tr>
            @empty
                <tr><td colspan="4" class="py-4 text-center text-gray-500">No payments yet.</td></tr>
            @endforelse
        </tbody></table>
    </x-card>
</x-layout>
