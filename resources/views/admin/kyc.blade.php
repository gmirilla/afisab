<x-layout title="KYC review">
    <h1 class="mb-4 text-2xl font-black text-brand-700">KYC awaiting review</h1>
    <div class="space-y-4">
    @forelse($profiles as $p)
        <x-card>
            <p class="font-black">{{ $p->user->name }} <span class="text-sm font-normal text-gray-500">{{ $p->user->email }} · {{ $p->phone }}</span></p>
            <p class="text-sm text-gray-600">DOB {{ $p->date_of_birth?->format('d M Y') }} · {{ $p->address }}<br>
               BVN {{ $p->bvn }} · {{ $p->bank_name }} {{ $p->account_number }} ({{ $p->account_name }})</p>
            <p class="mt-1"><a class="text-brand-700 underline" target="_blank" href="{{ route('admin.kyc.document', $p) }}">View ID document</a></p>
            <form method="POST" action="{{ route('admin.kyc.review', $p) }}" class="mt-3 flex flex-wrap items-center gap-2">@csrf
                <input name="note" placeholder="Note (required to reject)" class="w-64 rounded border px-2 py-1 text-sm">
                <button name="decision" value="approve" class="rounded bg-brand-700 px-3 py-1 text-sm font-bold text-white">Approve</button>
                <button name="decision" value="reject" class="rounded bg-red-600 px-3 py-1 text-sm font-bold text-white">Reject</button>
            </form>
        </x-card>
    @empty
        <x-card class="text-center text-gray-500">No submissions awaiting review.</x-card>
    @endforelse
    </div>
</x-layout>
