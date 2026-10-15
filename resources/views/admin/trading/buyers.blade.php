<x-layout title="Buyers">
    <h1 class="mb-4 text-2xl font-black text-brand-700">Buyers</h1>
    <div class="space-y-4">
    @foreach($buyers as $b)
        <x-card>
            @can('buyers.manage')
            <form method="POST" action="{{ route('admin.trading.buyers.save', $b) }}" class="grid gap-3 md:grid-cols-4">@csrf
                <x-field name="name" label="Name" :value="$b->name" required />
                <label class="block text-sm font-bold text-gray-700">Type
                    <select name="type" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 font-normal">@foreach(['local','processor','export'] as $t)<option value="{{ $t }}" @selected($b->type === $t)>{{ ucfirst($t) }}</option>@endforeach</select></label>
                <x-field name="contact_name" label="Contact" :value="$b->contact_name" />
                <x-field name="phone" label="Phone" :value="$b->phone" />
                <x-field name="email" label="Email" type="email" :value="$b->email" />
                <x-field name="address" label="Address" :value="$b->address" />
                <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($b->is_active)> Active</label>
                <div class="flex items-end"><x-btn>Save</x-btn></div>
            </form>
            @else
                <p><strong>{{ $b->name }}</strong> · {{ ucfirst($b->type) }} · {{ $b->contact_name }} · {{ $b->phone }} {{ $b->is_active ? '' : '(inactive)' }}</p>
            @endcan
        </x-card>
    @endforeach
    @can('buyers.manage')
        <x-card>
            <h2 class="mb-3 font-black text-brand-700">Add buyer</h2>
            <form method="POST" action="{{ route('admin.trading.buyers.save') }}" class="grid gap-3 md:grid-cols-4">@csrf
                <x-field name="name" label="Name" required />
                <label class="block text-sm font-bold text-gray-700">Type
                    <select name="type" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 font-normal"><option value="local">Local</option><option value="processor">Processor</option><option value="export">Export</option></select></label>
                <x-field name="contact_name" label="Contact" />
                <x-field name="phone" label="Phone" />
                <x-field name="email" label="Email" type="email" />
                <x-field name="address" label="Address" />
                <div class="flex items-end"><x-btn>Add buyer</x-btn></div>
            </form>
        </x-card>
    @endcan
    </div>
</x-layout>
