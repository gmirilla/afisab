@php use App\Support\Money; @endphp
<x-layout title="Invest">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Choose a plan</h1>
    <p class="mb-6 text-sm text-gray-600">Returns are a share of actual commodity trading profit and are not guaranteed. Capital is at risk.</p>
    <div class="grid gap-4 md:grid-cols-3">
        @foreach($plans as $plan)
            <x-card>
                <h2 class="text-lg font-black text-brand-700">{{ $plan->name }}</h2>
                <p class="text-sm text-gray-600">{{ $plan->duration_months }} months</p>
                <p class="mt-1 text-sm text-gray-600">{{ $plan->description }}</p>
                <p class="mt-2 text-sm">Minimum <strong>{{ Money::format($plan->minimum_amount) }}</strong></p>
                <form method="POST" action="{{ route('investor.investments.store') }}" class="mt-4 space-y-3">@csrf
                    <input type="hidden" name="investment_plan_id" value="{{ $plan->id }}">
                    <x-field name="amount" label="Amount (₦)" inputmode="decimal" :value="number_format($plan->minimum_amount / 100, 0, '.', '')" required />
                    <x-btn class="w-full">Subscribe</x-btn>
                </form>
            </x-card>
        @endforeach
    </div>
</x-layout>
