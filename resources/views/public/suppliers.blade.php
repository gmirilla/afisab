<x-public-layout title="For suppliers" description="Farmers, agricultural cooperatives and commodity aggregators: register your interest in selling produce to AFISAB NIG. LTD.">
    <x-page-banner eyebrow="For suppliers" title="Sell your produce to AFISAB" lead="We buy agricultural commodities from farmers, agricultural cooperatives, commodity aggregators and other suppliers." />

    <section class="mx-auto grid max-w-7xl gap-12 px-4 py-14 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <h2 class="font-display text-2xl font-bold text-brand-700">Who we buy from</h2>
            <ul class="mt-5 space-y-3 text-sm text-gray-700">
                <li class="flex gap-3"><x-icon name="check" class="h-5 w-5 shrink-0 text-brand-600" />Farmers</li>
                <li class="flex gap-3"><x-icon name="check" class="h-5 w-5 shrink-0 text-brand-600" />Agricultural cooperatives</li>
                <li class="flex gap-3"><x-icon name="check" class="h-5 w-5 shrink-0 text-brand-600" />Commodity aggregators</li>
                <li class="flex gap-3"><x-icon name="check" class="h-5 w-5 shrink-0 text-brand-600" />Other suppliers of agricultural commodities</li>
            </ul>

            <h2 class="mt-12 font-display text-2xl font-bold text-brand-700">How it works</h2>
            <ol class="mt-5 space-y-3 text-sm text-gray-700">
                <li><strong class="text-brand-700">1. Register your interest.</strong> Tell us what you have, how much, and where it is.</li>
                <li><strong class="text-brand-700">2. We contact you.</strong> Our procurement team follows up using the details you give.</li>
                <li><strong class="text-brand-700">3. Quality check on receipt.</strong> Moisture and grade are checked and recorded when goods arrive.</li>
                <li><strong class="text-brand-700">4. Payment on agreed terms.</strong> Terms are agreed with you before you deliver.</li>
            </ol>
        </div>
        <div class="lg:col-span-3">
            @include('public._enquiry-form', ['type' => 'supplier', 'commodities' => $commodities, 'heading' => 'Register your interest', 'intro' => 'Tell us what you can supply. Registering interest does not oblige either side to trade.', 'button' => 'Register interest'])
        </div>
    </section>
</x-public-layout>
