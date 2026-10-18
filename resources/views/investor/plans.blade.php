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
                    <fieldset>
                        <legend class="text-sm font-bold text-gray-700">How do you want your profit paid?</legend>
                        <div class="mt-1 space-y-2">
                            @foreach($frequencies as $f)
                                <label class="flex items-start gap-2 text-sm">
                                    <input type="radio" name="profit_frequency" value="{{ $f->value }}" class="mt-1" @checked(old('profit_frequency', 'monthly') === $f->value)>
                                    <span><strong>{{ $f->label() }}</strong><br><span class="text-xs text-gray-500">{{ $f->description() }}</span></span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-gray-500">This is fixed once you subscribe. Profit is the net result of the period, so with quarterly or one-off payment a weaker month is netted against a stronger one before your share is worked out.</p>
                    </fieldset>
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="accept_terms" value="1" required class="mt-1 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
                        <span>I have read and agree to the <a class="font-bold text-brand-700 underline" target="_blank" rel="noopener" href="{{ route('investor.agreement', ['plan' => $plan->id]) }}">Mudharabah Investment Agreement</a> (opens in a new tab). My choice of profit payment is part of it.</span>
                    </label>
                    <x-btn class="w-full">Subscribe</x-btn>
                </form>
            </x-card>
        @endforeach
    </div>
</x-layout>
