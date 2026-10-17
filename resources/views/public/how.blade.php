<x-public-layout title="How we work" description="How AFISAB NIG. LTD sources, aggregates, quality-checks, stores and supplies agricultural commodities.">
    <x-page-banner eyebrow="How we work" title="A disciplined path from supplier to buyer" lead="Every commodity we trade follows the same recorded path." />

    <section class="mx-auto max-w-4xl px-4 py-14">
        <ol class="space-y-10">
            @foreach([
                ['search', 'Source', 'We identify and buy produce from farmers, agricultural cooperatives, commodity aggregators and other suppliers.'],
                ['layers', 'Aggregate', 'Supply from different sources is consolidated into the volumes that industrial and bulk buyers need.'],
                ['shield', 'Quality-check', 'When goods are received, they are checked, and their moisture and grade are recorded. Stock that fails the check is not sold.'],
                ['box', 'Store', 'Each lot is held in a recorded warehouse, and its cost is tracked from the moment it is bought.'],
                ['truck', 'Supply', 'We sell to industrial buyers, manufacturers, feed mills, commodity merchants and other bulk purchasers.'],
            ] as $i => [$icon, $title, $text])
                <li class="flex gap-5">
                    <div class="flex flex-col items-center">
                        <span class="grid h-12 w-12 place-items-center rounded-full bg-brand-700 text-white"><x-icon :name="$icon" /></span>
                        @unless($loop->last)<span class="mt-2 w-px flex-1 bg-brand-100"></span>@endunless
                    </div>
                    <div class="pb-2">
                        <p class="text-xs font-bold uppercase tracking-widest text-gold-600">Step {{ $i + 1 }}</p>
                        <h2 class="font-display text-2xl font-bold text-brand-700">{{ $title }}</h2>
                        <p class="mt-1 leading-relaxed text-gray-600">{{ $text }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        <div class="mt-14 rounded-2xl bg-stone-50 p-8">
            <h2 class="font-display text-2xl font-bold text-brand-700">Controls we work under</h2>
            <ul class="mt-4 space-y-2 text-sm leading-relaxed text-gray-700">
                <li class="flex gap-3"><x-icon name="check" class="h-5 w-5 shrink-0 text-brand-600" />Purchases are proposed by one person and approved by another; larger purchases need a Director.</li>
                <li class="flex gap-3"><x-icon name="check" class="h-5 w-5 shrink-0 text-brand-600" />Payments are made by Accounts, never by the person who proposed the purchase.</li>
                <li class="flex gap-3"><x-icon name="check" class="h-5 w-5 shrink-0 text-brand-600" />Every purchase, sale and payment is recorded in an accounting ledger that cannot be edited after the fact.</li>
            </ul>
        </div>
    </section>

    @include('public._contact-band')
</x-public-layout>
