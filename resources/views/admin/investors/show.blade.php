@php
    use App\Support\Money;
    use App\Enums\KycStatus;
    use App\Enums\InvestmentStatus;
    use App\Enums\PaymentStatus;
    use App\Models\CapitalPayout;
    $kyc = $profile?->kyc_status ?? KycStatus::Incomplete;
    $kycBadge = match ($kyc) {
        KycStatus::Approved => 'bg-emerald-100 text-emerald-800',
        KycStatus::Submitted => 'bg-amber-100 text-amber-800',
        KycStatus::Rejected => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-600',
    };
@endphp
<x-layout :title="$user->name">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
        <div>
            <h1 class="text-2xl font-black text-brand-700">{{ $user->name }} <span class="ml-2 rounded px-2 py-0.5 align-middle text-xs font-bold {{ $kycBadge }}">KYC {{ $kyc->label() }}</span></h1>
            <p class="text-sm text-gray-600">{{ $user->email }}@if($user->email_verified_at) <span class="text-emerald-700">· verified</span>@else <span class="text-amber-700">· email not verified</span>@endif
                · registered {{ $user->created_at->format('d M Y') }}</p>
        </div>
        <a class="text-sm underline" href="{{ route('admin.investors') }}">← All investors</a>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <x-card><p class="text-xs text-gray-500">Principal held (active + matured)</p><p class="text-xl font-black">{{ Money::format($totals['principal']) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">Profit received</p><p class="text-xl font-black">{{ Money::format($totals['paid']) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">Profit approved, awaiting payment</p><p class="text-xl font-black">{{ Money::format($totals['payable']) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">Current value</p><p class="text-xl font-black text-brand-700">{{ Money::format($totals['value']) }}</p></x-card>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-2">
        <x-card>
            <h2 class="mb-2 font-black text-brand-700">Personal details</h2>
            @if($profile)
                <dl class="grid grid-cols-3 gap-y-1 text-sm">
                    <dt class="text-gray-500">Phone</dt><dd class="col-span-2">{{ $profile->phone ?: '—' }}</dd>
                    <dt class="text-gray-500">Date of birth</dt><dd class="col-span-2">{{ $profile->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                    <dt class="text-gray-500">Address</dt><dd class="col-span-2">{{ $profile->address ?: '—' }}</dd>
                    <dt class="text-gray-500">BVN</dt><dd class="col-span-2 font-mono">{{ $profile->bvn ? ($showFullBvn ? $profile->bvn : $profile->maskedBvn()) : '—' }}</dd>
                </dl>
            @else
                <p class="text-sm text-gray-500">This investor has not started their profile.</p>
            @endif
        </x-card>
        <x-card>
            <h2 class="mb-2 font-black text-brand-700">Bank account for payments to them</h2>
            @if($profile?->account_number)
                <p class="text-sm">{{ $profile->bank_name }} · <span class="font-mono">{{ $profile->account_number }}</span><br>{{ $profile->account_name }}</p>
            @else
                <p class="text-sm text-amber-700">No bank details yet, so profit and capital cannot be paid out.</p>
            @endif
            <h2 class="mb-1 mt-4 font-black text-brand-700">Verification</h2>
            <p class="text-sm">{{ $kyc->label() }}@if($profile?->kyc_reviewed_at) on {{ $profile->kyc_reviewed_at->format('d M Y') }} by {{ $profile->reviewer?->name ?? 'staff' }}@endif
                @if($profile?->kyc_note)<br><span class="text-gray-600">Note: {{ $profile->kyc_note }}</span>@endif
                @if($profile?->id_document_path)@can('kyc.review')<br><a class="text-brand-700 underline" target="_blank" href="{{ route('admin.kyc.document', $profile) }}">View ID document</a>@endcan @endif</p>
        </x-card>
    </div>

    <x-card class="mb-6 overflow-x-auto">
        <h2 class="mb-3 font-black text-brand-700">Investments</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr>
            <th class="py-2">Policy</th><th>Plan</th><th class="text-right">Amount</th><th>Status</th><th>Term</th><th>Profit paid</th><th class="text-right">Profit received / payable</th><th class="text-right">Current value</th><th></th></tr></thead><tbody>
        @forelse($investments as $i)
            @php($pos = $positions[$i->id])
            <tr class="border-b align-top last:border-0">
                <td class="py-2 font-mono">{{ $i->reference }}<br><span class="text-xs text-gray-500">{{ $i->certificate_number }}</span></td>
                <td>{{ $i->plan->name }}</td>
                <td class="text-right font-mono">{{ Money::format($i->amount) }}</td>
                <td>{{ $i->status->label() }}</td>
                <td class="text-xs text-gray-600">{{ $i->start_date?->format('d M Y') ?? 'Not started' }} → {{ $i->maturity_date?->format('d M Y') ?? '—' }}</td>
                <td>{{ $i->profit_frequency->label() }}</td>
                <td class="text-right font-mono">{{ Money::format($pos['paid']) }} / {{ Money::format($pos['payable']) }}</td>
                <td class="text-right font-mono">{{ in_array($i->status, [InvestmentStatus::Active, InvestmentStatus::Matured]) ? Money::format($pos['current_value']) : '—' }}</td>
                <td class="space-y-1 text-right">
                    @if($i->hasCertificate())@can('certificates.issue')
                        <a class="block text-brand-700 underline" href="{{ route('documents.certificate', $i) }}">Certificate</a>
                        <a class="block text-brand-700 underline" href="{{ route('documents.statement', $i) }}">Statement</a>
                    @endcan @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="py-6 text-center text-gray-500">No investments yet.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-card class="overflow-x-auto">
            <h2 class="mb-3 font-black text-brand-700">Payments in</h2>
            <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Date</th><th>Reference</th><th>Method</th><th class="text-right">Amount</th><th>Status</th></tr></thead><tbody>
            @forelse($payments as $p)
                <tr class="border-b last:border-0"><td class="py-2">{{ $p->created_at->format('d M Y') }}</td>
                    <td class="font-mono text-xs">{{ $p->reference }}<br><span class="text-gray-500">{{ $p->investment->reference }}</span></td>
                    <td>{{ ucfirst($p->method->value) }}</td><td class="text-right font-mono">{{ Money::format($p->amount) }}</td>
                    <td>{{ $p->status->label() }}@if($p->confirmed_at)<br><span class="text-xs text-gray-500">{{ $p->confirmed_at->format('d M Y') }}</span>@endif</td></tr>
            @empty
                <tr><td colspan="5" class="py-4 text-center text-gray-500">No payments.</td></tr>
            @endforelse
            </tbody></table>
        </x-card>

        <x-card class="overflow-x-auto">
            <h2 class="mb-3 font-black text-brand-700">Profit paid to them</h2>
            <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Run</th><th>Policy</th><th class="text-right">Their share</th><th>Status</th></tr></thead><tbody>
            @forelse($lines as $l)
                <tr class="border-b last:border-0"><td class="py-2">{{ $l->run->label() }}@if($l->coverage())<br><span class="text-xs text-gray-500">covers {{ $l->coverage() }}</span>@endif</td>
                    <td class="font-mono text-xs">{{ $l->investment->reference ?? '' }}</td>
                    <td class="text-right font-mono">{{ Money::format($l->investor_share) }}</td>
                    <td>{{ $l->status === 'paid' ? 'Paid '.$l->paid_at?->format('d M Y') : 'Awaiting payment' }}@if($l->payment_reference)<br><span class="font-mono text-xs text-gray-500">{{ $l->payment_reference }}</span>@endif</td></tr>
            @empty
                <tr><td colspan="4" class="py-4 text-center text-gray-500">No profit has been approved for this investor yet.</td></tr>
            @endforelse
            </tbody></table>
        </x-card>
    </div>

    @if($payouts->isNotEmpty())
        <x-card class="mb-6 overflow-x-auto">
            <h2 class="mb-3 font-black text-brand-700">Capital returned</h2>
            <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Policy</th><th class="text-right">Amount</th><th>Status</th><th>Reference</th></tr></thead><tbody>
            @foreach($payouts as $po)
                <tr class="border-b last:border-0"><td class="py-2 font-mono">{{ $po->investment->reference }}</td><td class="text-right font-mono">{{ Money::format($po->amount) }}</td>
                    <td>{{ ucfirst($po->status) }}@if($po->paid_at) · {{ $po->paid_at->format('d M Y') }}@endif</td><td class="font-mono text-xs">{{ $po->payment_reference ?? '—' }}</td></tr>
            @endforeach
            </tbody></table>
        </x-card>
    @endif

    @if($activity !== null)
        <x-card>
            <h2 class="mb-1 font-black text-brand-700">Recent activity</h2>
            <p class="mb-3 text-xs text-gray-500">From the audit trail: this investor, their policies and their payments. Latest 40. The full trail is under Audit.</p>
            <table class="w-full text-left text-sm"><tbody>
            @forelse($activity as $a)
                <tr class="border-b last:border-0"><td class="w-40 py-1.5 text-xs text-gray-500">{{ $a->created_at->format('d M Y H:i') }}</td><td>{{ $a->description }}</td><td class="text-xs text-gray-500">{{ $a->causer?->name ?? 'system' }}</td></tr>
            @empty
                <tr><td class="py-3 text-center text-gray-500">No activity recorded.</td></tr>
            @endforelse
            </tbody></table>
        </x-card>
    @endif
</x-layout>
