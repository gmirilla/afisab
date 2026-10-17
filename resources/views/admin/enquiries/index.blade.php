@php use App\Models\Enquiry; @endphp
<x-layout title="Website enquiries">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Website enquiries</h1>
    <p class="mb-4 text-sm text-gray-600">Quote requests, supplier interest and questions sent through the public website. Each is also emailed to {{ config('afisab.contact.enquiry_email') }}. New ones are listed first.
        <strong>{{ $newCount }}</strong> {{ $newCount === 1 ? 'is' : 'are' }} waiting for a first reply.</p>

    <x-card class="mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <label class="block text-sm font-bold text-gray-700">Search
                <input name="q" value="{{ $filters['q'] }}" placeholder="Name, company, email, phone, commodity" class="mt-1 block w-72 rounded border border-gray-300 px-3 py-2 font-normal"></label>
            <label class="block text-sm font-bold text-gray-700">Type
                <select name="type" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal"><option value="">All</option>
                    @foreach(Enquiry::TYPES as $key => $label)<option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>@endforeach</select></label>
            <label class="block text-sm font-bold text-gray-700">Status
                <select name="status" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal"><option value="">All</option>
                    @foreach(Enquiry::STATUSES as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach</select></label>
            <x-btn>Filter</x-btn> <a class="py-2 text-sm underline" href="{{ route('admin.enquiries') }}">Clear</a>
        </form>
    </x-card>

    <x-card class="overflow-x-auto">
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Received</th><th>From</th><th>Type</th><th>About</th><th>Status</th></tr></thead><tbody>
        @forelse($enquiries as $e)
            <tr class="border-b align-top last:border-0">
                <td class="py-2 text-gray-600">{{ $e->created_at->format('d M Y H:i') }}</td>
                <td><a class="font-bold text-brand-700 underline" href="{{ route('admin.enquiries.show', $e) }}">{{ $e->name }}</a>@if($e->company)<br><span class="text-xs text-gray-500">{{ $e->company }}</span>@endif<br><span class="text-xs text-gray-500">{{ $e->phone }}</span></td>
                <td>{{ $e->label() }}</td>
                <td>{{ $e->commodity ?: '—' }}@if($e->quantity)<br><span class="text-xs text-gray-500">{{ $e->quantity }}</span>@endif</td>
                <td><span class="rounded px-2 py-0.5 text-xs font-bold {{ $e->status === Enquiry::NEW ? 'bg-amber-100 text-amber-800' : ($e->status === Enquiry::CONTACTED ? 'bg-sky-100 text-sky-800' : 'bg-gray-100 text-gray-600') }}">{{ Enquiry::STATUSES[$e->status] }}</span>
                    @if($e->handler)<br><span class="text-xs text-gray-500">{{ $e->handler->name }}</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-8 text-center text-gray-500">No enquiries match.</td></tr>
        @endforelse
        </tbody></table>
        <div class="mt-4">{{ $enquiries->links() }}</div>
    </x-card>
</x-layout>
