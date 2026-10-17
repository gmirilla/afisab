<x-public-layout title="Risk notice" description="Risks of agricultural commodity trading and the status of information on the AFISAB NIG. LTD website.">
    <x-page-banner eyebrow="Legal" title="Risk notice" />
    <section class="mx-auto max-w-3xl space-y-6 px-4 py-14 leading-relaxed text-gray-700">
        <p>Agricultural commodity trading carries risk. Prices move with supply, demand, weather and the season. Quality can change in storage and transport. Costs, regulations and logistics change. Any business that buys and sells commodities, including AFISAB NIG. LTD, can make a loss as well as a profit.</p>
        <p>The information on this website is general and is not financial, investment or legal advice. Nothing on it is an invitation to invest or a promise of any return.</p>
        @if(config('afisab.public_investor_page'))
            <p>Investors in an AFISAB trading programme receive a share of actual trading profit. Returns are not guaranteed and capital is at risk.</p>
        @endif
        <p>If you are unsure how a commodity transaction or any other matter affects you, take independent advice before you proceed.</p>
    </section>
</x-public-layout>
