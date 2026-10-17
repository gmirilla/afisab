@php
    use App\Support\Money;
    use App\Enums\KycStatus;
    $badge = fn (?KycStatus $s) => match ($s) {
        KycStatus::Approved => 'bg-emerald-100 text-emerald-800',
        KycStatus::Submitted => 'bg-amber-100 text-amber-800',
        KycStatus::Rejected => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-600',
    };
@endphp
<x-layout title="Investors">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
        <div>
            <h1 class="text-2xl font-black text-brand-700">Investors</h1>
            <p class="text-sm text-gray-600">Everyone who has registered as an investor, with their verification status and holdings. Open a name for their full history. This list is read-only.</p>
        </div>
        <a class="rounded border border-brand-700 px-4 py-2 text-sm font-bold text-brand-700" href="{{ route('admin.investors.export', array_filter($filters)) }}">Download CSV</a>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <x-card><p class="text-xs text-gray-500">Investors</p><p class="text-2xl font-black">{{ number_format($summary['investors']) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">With an active policy</p><p class="text-2xl font-black">{{ number_format($summary['with_active']) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">KYC awaiting review</p><p class="text-2xl font-black {{ $summary['kyc_pending'] ? 'text-amber-700' : '' }}">{{ number_format($summary['kyc_pending']) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">Active principal</p><p class="text-2xl font-black text-brand-700">{{ Money::format($summary['principal']) }}</p></x-card>
    </div>

    <x-card class="mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <label class="block text-sm font-bold text-gray-700">Search
                <input name="q" value="{{ $filters['q'] }}" placeholder="Name, email, phone or policy reference" class="mt-1 block w-72 rounded border border-gray-300 px-3 py-2 font-normal"></label>
            <label class="block text-sm font-bold text-gray-700">KYC
                <select name="kyc" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal"><option value="">Any</option>
                    @foreach(KycStatus::cases() as $s)<option value="{{ $s->value }}" @selected($filters['kyc'] === $s->value)>{{ $s->label() }}</option>@endforeach</select></label>
            <label class="block text-sm font-bold text-gray-700">Holdings
                <select name="status" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal"><option value="">Any</option>
                    <option value="active" @selected($filters['status'] === 'active')>Has an active policy</option>
                    <option value="none" @selected($filters['status'] === 'none')>No active or matured policy</option></select></label>
            <label class="block text-sm font-bold text-gray-700">Profit paid
                <select name="frequency" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal"><option value="">Any</option>
                    @foreach($frequencies as $f)<option value="{{ $f->value }}" @selected($filters['frequency'] === $f->value)>{{ $f->label() }}</option>@endforeach</select></label>
            <label class="block text-sm font-bold text-gray-700">Sort
                <select name="sort" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal">
                    <option value="newest" @selected($filters['sort'] === 'newest')>Newest first</option>
                    <option value="name" @selected($filters['sort'] === 'name')>Name</option>
                    <option value="invested" @selected($filters['sort'] === 'invested')>Largest holding</option></select></label>
            <x-btn>Filter</x-btn>
            <a class="py-2 text-sm underline" href="{{ route('admin.investors') }}">Clear</a>
        </form>
    </x-card>

    <x-card class="overflow-x-auto">
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr>
            <th class="py-2">Investor</th><th>KYC</th><th class="text-right">Policies</th><th class="text-right">Active principal</th><th class="text-right">Profit paid</th><th class="text-right">Profit payable</th><th>Registered</th></tr></thead><tbody>
        @forelse($users as $u)
            @php($kyc = $u->investorProfile?->kyc_status ?? KycStatus::Incomplete)
            <tr class="border-b last:border-0">
                <td class="py-2"><a class="font-bold text-brand-700 underline" href="{{ route('admin.investors.show', $u) }}">{{ $u->name }}</a><br><span class="text-xs text-gray-500">{{ $u->email }}@if($u->investorProfile?->phone) · {{ $u->investorProfile->phone }}@endif</span></td>
                <td><span class="rounded px-2 py-0.5 text-xs font-bold {{ $badge($kyc) }}">{{ $kyc->label() }}</span></td>
                <td class="text-right">{{ $u->investments_count }}@if($u->active_count)<span class="text-xs text-gray-500"> ({{ $u->active_count }} active)</span>@endif</td>
                <td class="text-right font-mono">{{ Money::format((int) $u->active_principal) }}</td>
                <td class="text-right font-mono">{{ Money::format($money[$u->id]['paid']) }}</td>
                <td class="text-right font-mono">{{ Money::format($money[$u->id]['payable']) }}</td>
                <td class="text-gray-600">{{ $u->created_at->format('d M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-8 text-center text-gray-500">No investors match.</td></tr>
        @endforelse
        </tbody></table>
        <div class="mt-4">{{ $users->links() }}</div>
    </x-card>
</x-layout>
