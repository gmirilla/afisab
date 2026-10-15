@php use App\Support\Money; @endphp
<x-layout title="Plans">
    <h1 class="mb-4 text-2xl font-black text-brand-700">Investment plans</h1>
    <div class="space-y-4">
    @foreach($plans as $plan)
        <x-card>
            <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="grid gap-3 md:grid-cols-6">@csrf @method('PUT')
                <x-field name="name" label="Name" :value="$plan->name" class="md:col-span-2" />
                <x-field name="duration_months" label="Months" type="number" :value="$plan->duration_months" />
                <x-field name="minimum_amount" label="Minimum (₦)" :value="number_format($plan->minimum_amount / 100, 0, '.', '')" />
                <x-field name="description" label="Description" :value="$plan->description" />
                <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($plan->is_active)> Active</label>
                <div class="md:col-span-6"><x-btn>Save</x-btn></div>
            </form>
        </x-card>
    @endforeach
        <x-card>
            <h2 class="mb-3 font-black text-brand-700">Add plan</h2>
            <form method="POST" action="{{ route('admin.plans.store') }}" class="grid gap-3 md:grid-cols-6">@csrf
                <x-field name="name" label="Name" class="md:col-span-2" />
                <x-field name="duration_months" label="Months" type="number" />
                <x-field name="minimum_amount" label="Minimum (₦)" value="500000" />
                <x-field name="description" label="Description" />
                <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                <div class="md:col-span-6"><x-btn>Add plan</x-btn></div>
            </form>
        </x-card>
    </div>
</x-layout>
