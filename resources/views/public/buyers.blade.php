<x-public-layout title="For buyers" description="Supply of agricultural commodities to industrial buyers, manufacturers, feed mills and commodity merchants in Nigeria. Request a quote from AFISAB NIG. LTD.">
    <x-page-banner eyebrow="For buyers" title="Reliable commodity supply for your business" lead="AFISAB supplies agricultural commodities to industrial buyers, manufacturers, feed mills, commodity merchants and other bulk purchasers." />

    <section class="mx-auto grid max-w-7xl gap-12 px-4 py-14 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <h2 class="font-display text-2xl font-bold text-brand-700">What you can expect</h2>
            <ul class="mt-6 space-y-5">
                @foreach([
                    ['search', 'Direct sourcing', 'Supply is bought from farmers, agricultural cooperatives and commodity aggregators.'],
                    ['shield', 'Quality-checked on receipt', 'Moisture and grade are recorded for each lot, and stock that fails the check is not sold.'],
                    ['box', 'Held in recorded storage', 'Each lot is recorded against the warehouse that holds it, from purchase to sale.'],
                    ['truck', 'Bulk supply', 'We aggregate supply to the volumes that industrial buyers need.'],
                ] as [$icon, $title, $text])
                    <li class="flex gap-4"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700"><x-icon :name="$icon" class="h-5 w-5" /></span>
                        <div><p class="font-black text-brand-700">{{ $title }}</p><p class="mt-0.5 text-sm leading-relaxed text-gray-600">{{ $text }}</p></div></li>
                @endforeach
            </ul>

            <h2 class="mt-12 font-display text-2xl font-bold text-brand-700">How it works</h2>
            <ol class="mt-5 space-y-3 text-sm text-gray-700">
                <li><strong class="text-brand-700">1. Tell us what you need.</strong> Commodity, quantity and where it must be delivered.</li>
                <li><strong class="text-brand-700">2. We respond.</strong> With availability and terms for your requirement.</li>
                <li><strong class="text-brand-700">3. Agree and supply.</strong> We confirm the order and arrange supply with you.</li>
            </ol>
        </div>
        <div class="lg:col-span-3">
            @include('public._enquiry-form', ['type' => 'quote', 'commodities' => $commodities, 'heading' => 'Request a quote', 'intro' => 'Tell us what you need. A member of our team will contact you.', 'button' => 'Send request'])
        </div>
    </section>
</x-public-layout>
