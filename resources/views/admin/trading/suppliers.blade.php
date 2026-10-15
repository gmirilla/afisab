<x-layout title="Suppliers">
    <h1 class="mb-4 text-2xl font-black text-brand-700">Suppliers: farmers and aggregators</h1>
    <div class="space-y-4">
    @foreach($suppliers as $s)
        <x-card>
            @can('suppliers.manage')
            <form method="POST" action="{{ route('admin.trading.suppliers.save', $s) }}" class="grid gap-3 md:grid-cols-4">@csrf
                <x-field name="name" label="Name" :value="$s->name" required />
                <label class="block text-sm font-bold text-gray-700">Type
                    <select name="type" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 font-normal">@foreach(['farmer','aggregator','supplier'] as $t)<option value="{{ $t }}" @selected($s->type === $t)>{{ ucfirst($t) }}</option>@endforeach</select></label>
                <x-field name="phone" label="Phone" :value="$s->phone" />
                <x-field name="location" label="Location" :value="$s->location" />
                <x-field name="bank_name" label="Bank" :value="$s->bank_name" />
                <x-field name="account_number" label="Account number" :value="$s->account_number" maxlength="10" />
                <x-field name="account_name" label="Account name" :value="$s->account_name" />
                <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($s->is_active)> Active</label>
                <div class="md:col-span-4"><x-btn>Save</x-btn></div>
            </form>
            @else
                <p><strong>{{ $s->name }}</strong> · {{ ucfirst($s->type) }} · {{ $s->location }} · {{ $s->phone }} {{ $s->is_active ? '' : '(inactive)' }}</p>
            @endcan
        </x-card>
    @endforeach
    @can('suppliers.manage')
        <x-card>
            <h2 class="mb-3 font-black text-brand-700">Add supplier</h2>
            <form method="POST" action="{{ route('admin.trading.suppliers.save') }}" class="grid gap-3 md:grid-cols-4">@csrf
                <x-field name="name" label="Name" required />
                <label class="block text-sm font-bold text-gray-700">Type
                    <select name="type" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 font-normal"><option value="aggregator">Aggregator</option><option value="farmer">Farmer</option><option value="supplier">Supplier</option></select></label>
                <x-field name="phone" label="Phone" />
                <x-field name="location" label="Location" />
                <x-field name="bank_name" label="Bank" />
                <x-field name="account_number" label="Account number" maxlength="10" />
                <x-field name="account_name" label="Account name" />
                <div class="flex items-end"><x-btn>Add supplier</x-btn></div>
            </form>
        </x-card>
    @endcan
    </div>
</x-layout>
