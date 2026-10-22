@php use App\Support\Money; @endphp
<x-layout title="Plans">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Investment plans</h1>
    <p class="mb-4 text-sm text-gray-600">Investors see the active plans in this order, top to bottom. Use the arrows to rearrange them. A new plan is added at the end.</p>
    <div class="space-y-4">
    @foreach($plans as $plan)
        <x-card>
            <div class="mb-3 flex items-center gap-2 text-sm">
                <span class="rounded bg-brand-700 px-2 py-0.5 font-black text-white">{{ $loop->iteration }}</span>
                @unless($plan->is_active)<span class="rounded bg-stone-200 px-2 py-0.5 text-xs text-gray-600">Hidden from investors</span>@endunless
                <span class="ml-auto flex gap-1">
                    @unless($loop->first)<form method="POST" action="{{ route('admin.plans.move', $plan) }}">@csrf<input type="hidden" name="direction" value="up"><x-btn variant="ghost" aria-label="Move {{ $plan->name }} up">&uarr; Move up</x-btn></form>@endunless
                    @unless($loop->last)<form method="POST" action="{{ route('admin.plans.move', $plan) }}">@csrf<input type="hidden" name="direction" value="down"><x-btn variant="ghost" aria-label="Move {{ $plan->name }} down">&darr; Move down</x-btn></form>@endunless
                </span>
            </div>
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
