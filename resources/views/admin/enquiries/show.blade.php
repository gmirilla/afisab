@php use App\Models\Enquiry; @endphp
<x-layout :title="'Enquiry from '.$enquiry->name">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-black text-brand-700">{{ $enquiry->label() }} <span class="text-base font-normal text-gray-500">from {{ $enquiry->name }}</span></h1>
        <a class="text-sm underline" href="{{ route('admin.enquiries') }}">← All enquiries</a>
    </div>
    @if($errors->has('enquiry'))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('enquiry') }}</p>@endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <dl class="grid grid-cols-3 gap-y-2 text-sm">
                <dt class="text-gray-500">Received</dt><dd class="col-span-2">{{ $enquiry->created_at->format('d M Y H:i') }}</dd>
                <dt class="text-gray-500">Name</dt><dd class="col-span-2">{{ $enquiry->name }}</dd>
                <dt class="text-gray-500">Company</dt><dd class="col-span-2">{{ $enquiry->company ?: '—' }}</dd>
                <dt class="text-gray-500">Phone</dt><dd class="col-span-2">@if($enquiry->phone)<a class="text-brand-700 underline" href="tel:{{ $enquiry->phone }}">{{ $enquiry->phone }}</a>@else — @endif</dd>
                <dt class="text-gray-500">Email</dt><dd class="col-span-2">@if($enquiry->email)<a class="text-brand-700 underline" href="mailto:{{ $enquiry->email }}">{{ $enquiry->email }}</a>@else — @endif</dd>
                @if($enquiry->commodity)<dt class="text-gray-500">Commodity</dt><dd class="col-span-2">{{ $enquiry->commodity }}</dd>@endif
                @if($enquiry->quantity)<dt class="text-gray-500">Quantity</dt><dd class="col-span-2">{{ $enquiry->quantity }}</dd>@endif
                @if($enquiry->location)<dt class="text-gray-500">Location</dt><dd class="col-span-2">{{ $enquiry->location }}</dd>@endif
                <dt class="text-gray-500">Consent to contact</dt><dd class="col-span-2">Given {{ $enquiry->consented_at->format('d M Y H:i') }}</dd>
            </dl>
            @if($enquiry->message)<div class="mt-5 border-t pt-4"><p class="text-xs font-bold uppercase tracking-wider text-gray-500">Message</p><p class="mt-1 whitespace-pre-line text-sm">{{ $enquiry->message }}</p></div>@endif
        </x-card>

        <div class="space-y-6">
            <x-card>
                <h2 class="mb-3 font-black text-brand-700">Follow-up</h2>
                <form method="POST" action="{{ route('admin.enquiries.update', $enquiry) }}" class="space-y-3">@csrf @method('PUT')
                    <label class="block text-sm font-bold text-gray-700">Status
                        <select name="status" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 font-normal">
                            @foreach(Enquiry::STATUSES as $key => $label)<option value="{{ $key }}" @selected($enquiry->status === $key)>{{ $label }}</option>@endforeach</select></label>
                    <label class="block text-sm font-bold text-gray-700">Internal note
                        <textarea name="internal_note" rows="4" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 font-normal">{{ old('internal_note', $enquiry->internal_note) }}</textarea></label>
                    <x-btn>Save</x-btn>
                    @if($enquiry->handler)<p class="text-xs text-gray-500">Last updated by {{ $enquiry->handler->name }}, {{ $enquiry->handled_at?->format('d M Y H:i') }}.</p>@endif
                </form>
            </x-card>

            @if($enquiry->type === Enquiry::SUPPLIER)
                <x-card>
                    <h2 class="mb-2 font-black text-brand-700">Supplier record</h2>
                    @if($enquiry->supplier)
                        <p class="text-sm">Created as <a class="font-bold text-brand-700 underline" href="{{ route('admin.trading.suppliers') }}">{{ $enquiry->supplier->name }}</a>.</p>
                    @else
                        <p class="mb-3 text-sm text-gray-600">Once you have spoken to them, add them as a supplier so Procurement can buy from them.</p>
                        @can('suppliers.manage')
                            <form method="POST" action="{{ route('admin.enquiries.supplier', $enquiry) }}" data-confirm="Create a supplier record from this enquiry?">@csrf<x-btn variant="ghost">Create supplier</x-btn></form>
                        @else
                            <p class="text-xs text-gray-500">Procurement can create the supplier record.</p>
                        @endcan
                    @endif
                </x-card>
            @endif
        </div>
    </div>
</x-layout>
