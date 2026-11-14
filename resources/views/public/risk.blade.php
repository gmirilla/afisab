<x-site-layout title="Risk notice" description="Risks of agricultural commodity trading and the status of information on the AFISAB NIG. LTD website.">
    <section class="trade-banner trade-banner--form">
      <div class="wrap trade-banner-grid">
        <div>
          <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><span aria-current="page">Risk notice</span></nav>
          <p class="eyebrow">LEGAL</p>
          <h1 class="display">Risk notice</h1>
        </div>
      </div>
    </section>
    <section class="site-notice wrap">
        <p>Agricultural commodity trading carries risk. Prices move with supply, demand, weather and the season. Quality can change in storage and transport. Costs, regulations and logistics change. Any business that buys and sells commodities, including AFISAB NIG. LTD, can make a loss as well as a profit.</p>
        <p>The information on this website is general and is not financial, investment or legal advice. Nothing on it is an invitation to invest or a promise of any return.</p>
        @if(config('afisab.public_investor_page'))
            <p>Investors in an AFISAB trading programme receive a share of actual trading profit. Returns are not guaranteed and capital is at risk.</p>
        @endif
        <p>If you are unsure how a commodity transaction or any other matter affects you, take independent advice before you proceed.</p>
    </section>
</x-site-layout>
