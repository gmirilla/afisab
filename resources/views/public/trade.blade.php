<x-public-layout title="What we trade" description="The agricultural commodities AFISAB NIG. LTD trades and supplies: grains, oil seeds, tree crops and cash crops, including maize, soybeans, sesame, groundnuts, cashew, shea and ginger.">
    <x-page-banner eyebrow="Our commodities" title="What we trade" lead="A broad portfolio of Nigerian agricultural commodities, sourced, aggregated and supplied in volume." />

    <section class="mx-auto max-w-7xl px-4 py-14">
        <div class="space-y-12">
            @foreach($groups as $group)
                <div>
                    <h2 class="border-b border-stone-200 pb-2 font-display text-2xl font-bold text-brand-700">{{ $group['label'] }}</h2>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach($group['items'] as $item)
                            <a href="{{ route('public.buyers', ['commodity' => $item]) }}#enquiry"
                               class="group flex items-center justify-between rounded-lg border border-stone-200 bg-white px-4 py-3 text-sm font-bold text-gray-800 transition hover:border-brand-600 hover:bg-brand-50">
                                <span>{{ $item }}</span>
                                <span class="text-xs font-normal text-gray-400 group-hover:text-brand-700">Request a quote</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        <p class="mt-12 max-w-3xl rounded-lg bg-stone-50 p-5 text-sm leading-relaxed text-gray-600">Availability, grade and price change with the season and the market. Select a commodity above, or <a class="font-bold text-brand-700 underline" href="{{ route('public.buyers') }}#enquiry">send us your requirement</a>, and we will respond with current availability and terms.</p>
    </section>

    @include('public._contact-band')
</x-public-layout>
