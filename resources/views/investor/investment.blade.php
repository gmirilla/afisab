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

    @php($policy = $investment->currentPolicyDocument())
    @if($errors->has('policy'))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('policy') }}</p>@endif
    @if($errors->has('receipt'))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('receipt') }}</p>@endif
    @if($investment->hasCertificate())
        @php($receipt = $investment->currentReceipt())
        <x-card class="mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-gray-500">Investment Receipt</p>
                    @if($receipt)
                        <p class="font-mono text-lg font-black text-brand-700">{{ $receipt->receipt_number }}</p>
                        <p class="text-xs text-gray-500">Money received {{ $receipt->received_on->format('d M Y') }}@if($receipt->version > 1) · version {{ $receipt->version }}@endif</p>
                    @else
                        <p class="text-sm text-amber-700">Your receipt is being prepared. It is emailed to you with your agreement as soon as it is ready.</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($receipt)<a class="rounded bg-brand-700 px-4 py-2 text-sm font-bold text-white" href="{{ route('investor.receipt.download', $investment) }}">Download receipt (PDF)</a>@endif
                    <form method="POST" action="{{ route('investor.receipt.email', $investment) }}">@csrf<x-btn variant="ghost">{{ $receipt ? 'Email me a copy' : 'Prepare and email it to me' }}</x-btn></form>
                </div>
            </div>
        </x-card>

        <x-card class="mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-gray-500">Mudharabah Investment Agreement</p>
                    @if($policy)
                        <p class="font-mono text-lg font-black text-brand-700">{{ $policy->policy_number }}</p>
                        <p class="text-xs text-gray-500">Version {{ $policy->version }}, issued {{ $policy->issued_at->format('d M Y') }}@if($investment->terms_accepted_at) · you agreed to its terms on {{ $investment->terms_accepted_at->format('d M Y H:i') }}@endif</p>
                    @else
                        <p class="text-sm text-amber-700">Your agreement is being prepared. It is emailed to you as soon as it is ready.</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($policy)<a class="rounded bg-brand-700 px-4 py-2 text-sm font-bold text-white" href="{{ route('investor.policy.download', $investment) }}">Download agreement (PDF)</a>@endif
                    <form method="POST" action="{{ route('investor.policy.email', $investment) }}">@csrf<x-btn variant="ghost">{{ $policy ? 'Email me a copy' : 'Prepare and email it to me' }}</x-btn></form>
                </div>
            </div>
        </x-card>

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
        <p class="mb-6 text-sm text-gray-600">Profit payment: <strong>{{ $investment->profit_frequency->label() }}</strong>. {{ $investment->profit_frequency->description() }}
            Next calculation: <strong>{{ $next['cutoff']->format('d F Y') }}</strong> · payment by <strong>{{ $next['pay_by']->format('d F Y') }}</strong>.</p>

        @if($distributions->isNotEmpty())
            <x-card class="mb-6">
                <h2 class="mb-3 font-black text-brand-700">Profit payments</h2>
                <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Paid in</th><th>Your share</th><th>Status</th><th>Reference</th></tr></thead><tbody>
                    @foreach($distributions as $d)
                        <tr class="border-b last:border-0"><td class="py-2">{{ $d->run->label() }}@if($d->coverage())<br><span class="text-xs text-gray-500">profit for {{ $d->coverage() }}</span>@endif</td><td>{{ Money::format($d->investor_share) }}</td>
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
            <p class="mb-3 text-sm text-gray-600">Your funds are deployed into commodity trading cycles. On the 27th of each month the net profit is calculated; your share is approved and paid {{ match($investment->profit_frequency) { \App\Enums\ProfitFrequency::Monthly => 'by the end of that month', \App\Enums\ProfitFrequency::Quarterly => 'every 3 months (and at maturity)', \App\Enums\ProfitFrequency::HalfYearly => 'every 6 months (and at maturity)', \App\Enums\ProfitFrequency::Annually => 'once a year (and at maturity)', default => 'in one payment, at maturity' } }}. Returns are not guaranteed.</p>
            <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Cycle</th><th>Closed</th><th>Your capital in cycle</th><th>Included in</th></tr></thead><tbody>
                @forelse($entries as $e)
                    <tr class="border-b last:border-0">
                        <td class="py-2 font-mono">{{ $e->cycle_reference }}</td><td>{{ $e->recorded_at->format('d M Y') }}</td>
                        <td>{{ Money::format($e->capital_allocated) }}</td>
                        <td>@if($e->run) {{ $e->run->label() }} distribution @else <span class="text-amber-700">{{ $investment->profit_frequency === \App\Enums\ProfitFrequency::Monthly ? 'Next monthly calculation' : 'Held until your next '.strtolower($investment->profit_frequency->label()).' payment' }}</span> @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-gray-500">No trading cycle has closed for this investment yet.</td></tr>
                @endforelse
            </tbody></table>
        </x-card>
    @elseif($investment->terms_accepted_at)
        <x-card class="mb-6 text-sm text-gray-700">You agreed to the Mudharabah Investment Agreement on {{ $investment->terms_accepted_at->format('d M Y H:i') }}.
            Your signed copy is issued, and emailed to you, once your payment is confirmed.
            <a class="font-bold text-brand-700 underline" target="_blank" rel="noopener" href="{{ route('investor.agreement', ['plan' => $investment->investment_plan_id, 'frequency' => $investment->profit_frequency->value, 'amount' => number_format($investment->amount / 100, 2, '.', '')]) }}">Read it again</a></x-card>
    @endif

    @php($withdrawal = $investment->openWithdrawal())
    @php($lastWithdrawal = $investment->withdrawalRequests()->first())
    @if($errors->has('withdrawal'))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('withdrawal') }}</p>@endif
    @if($investment->status === InvestmentStatus::Active)
        <x-card class="mb-6">
            <h2 class="font-black text-brand-700">Early withdrawal</h2>
            @if($withdrawal)
                <p class="mt-2 text-sm text-gray-700">You gave notice on <strong>{{ $withdrawal->requested_at->format('d M Y') }}</strong>. Your notice period runs until <strong>{{ $withdrawal->notice_ends_on->format('d M Y') }}</strong>.
                    Your investment keeps earning until then. As your agreement says, the withdrawal is subject to the availability of funds and the settlement of ongoing transactions: if any of your capital is still in a trading cycle when the notice ends, it waits until that cycle closes. We will email you when your investment matures and your payout is prepared.</p>
                <form method="POST" action="{{ route('investor.withdrawal.cancel', $withdrawal) }}" class="mt-3" data-confirm="Cancel your notice of early withdrawal?">@csrf @method('DELETE')<x-btn variant="ghost">Cancel my notice</x-btn></form>
            @else
                @php($months = (int) ($investment->terms['early_withdrawal_notice_months'] ?? config('afisab.policy.early_withdrawal_notice_months')))
                <p class="mt-2 text-sm text-gray-700">You can ask for your capital back before {{ $investment->maturity_date?->format('d M Y') }}. Your agreement (clause 9.2) needs <strong>{{ $months }} month{{ $months === 1 ? '' : 's' }}' notice</strong> and the withdrawal is subject to the availability of funds and the settlement of ongoing transactions.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600">
                    <li>Your investment stays active and keeps earning during the notice, but its capital is not placed in new trading cycles.</li>
                    <li>When the notice ends, and none of your capital is still in a trading cycle, your investment matures early. Profit held back for your payment frequency is settled in that month's profit run, and no profit is earned after that.</li>
                    <li>Your capital is then paid to your bank account after a second person approves the payout. Capital is not guaranteed (clause 9.3).</li>
                    <li>You can cancel the notice at any time before your investment matures.</li>
                </ul>
                <form method="POST" action="{{ route('investor.withdrawal.store', $investment) }}" class="mt-4 space-y-3 border-t pt-4">@csrf
                    <x-field name="reason" label="Reason (optional)" :value="old('reason')" maxlength="500" />
                    <label class="flex items-start gap-2 text-sm text-gray-700"><input type="checkbox" name="understand" value="1" required class="mt-1 rounded border-gray-300 text-brand-700 focus:ring-brand-600"><span>I understand how early withdrawal works and I want to give notice.</span></label>
                    <x-btn variant="danger">Give notice of early withdrawal</x-btn>
                </form>
            @endif
        </x-card>
    @elseif($lastWithdrawal && in_array($lastWithdrawal->status, ['matured', 'completed']))
        <x-card class="mb-6 text-sm text-gray-700">You gave notice of early withdrawal on {{ $lastWithdrawal->requested_at->format('d M Y') }}. Your investment matured early on {{ $lastWithdrawal->matured_at?->format('d M Y') }}.
            {{ $lastWithdrawal->status === 'completed' ? 'Your capital was returned on '.$lastWithdrawal->completed_at?->format('d M Y').'.' : 'Your capital payout is being prepared; we will email you when it has been paid.' }}</x-card>
    @endif

    @if($investment->status === InvestmentStatus::PendingPayment)
        <div class="grid gap-4 md:grid-cols-2">
            <x-card>
                <h2 class="font-black text-brand-700">Pay online (card / bank)</h2>
                @if(app(\App\Services\Payments\PaystackSettings::class)->enabled())
                    <p class="my-2 text-sm text-gray-600">Secure checkout by Paystack.</p>
                    <form method="POST" action="{{ route('investor.payments.paystack', $investment) }}">@csrf<x-btn>Pay {{ Money::format($investment->amount) }}</x-btn></form>
                @else
                    <p class="my-2 text-sm text-gray-600">Online payment is not available right now. Please pay by bank transfer.</p>
                @endif
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
                @php($awaiting = $investment->payments->where('method', PaymentMethod::Transfer)->firstWhere('status', PaymentStatus::AwaitingConfirmation))
                @if($errors->has('proof'))<p class="mb-2 rounded bg-red-50 p-2 text-sm text-red-700">{{ $errors->first('proof') }}</p>@endif
                @if($awaiting)<p class="mb-2 text-sm text-amber-700">Receipt submitted; awaiting confirmation by Accounts. You can upload a different receipt below if you sent the wrong file.</p>@endif
                <form method="POST" action="{{ route('investor.payments.transfer', $investment) }}" enctype="multipart/form-data" class="space-y-3 border-t pt-3">@csrf
                    <x-field name="proof" label="{{ $awaiting ? 'Replace your receipt' : 'After paying, upload your transfer receipt' }} (JPG, PNG or PDF, max 4MB)" type="file" required />
                    <x-btn variant="{{ $awaiting ? 'ghost' : 'primary' }}">{{ $awaiting ? 'Replace receipt' : 'I have made the transfer: submit receipt' }}</x-btn>
                </form>
            </x-card>
        </div>
        <form method="POST" action="{{ route('investor.investments.cancel', $investment) }}" class="mt-4">@csrf @method('DELETE')<x-btn variant="ghost">Cancel this subscription</x-btn></form>
    @endif

    <x-card class="mt-6">
        <h2 class="mb-3 font-black text-brand-700">Payments</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Reference</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead><tbody>
            @forelse($investment->payments->reject(fn ($p) => $p->method === PaymentMethod::Transfer && $p->status === PaymentStatus::Pending && ! $p->proof_path) as $p)
                <tr class="border-b last:border-0"><td class="py-2 font-mono">{{ $p->reference }}</td><td>{{ ucfirst($p->method->value) }}</td><td>{{ Money::format($p->amount) }}</td><td>{{ $p->status->label() }}</td></tr>
            @empty
                <tr><td colspan="4" class="py-4 text-center text-gray-500">No payments yet.</td></tr>
            @endforelse
        </tbody></table>
    </x-card>
</x-layout>
