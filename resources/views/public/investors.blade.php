<x-site-layout title="Investors" description="Participate in AFISAB NIG. LTD's agricultural commodity trading programme as a registered investor.">
    <section class="trade-banner trade-banner--form">
      <div class="wrap trade-banner-grid">
        <div>
          <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><span aria-current="page">The AFISAB investor programme</span></nav>
          <p class="eyebrow">INVESTORS</p>
          <h1 class="display">The AFISAB investor programme</h1><p>Participate in Nigeria's agricultural commodity trade through professionally managed, quality-assured trading cycles.</p>
        </div>
      </div>
    </section>
    <section class="site-notice wrap">
        <h2>Minimum ₦500,000</h2>
        <p>Choose a plan that suits you. The plans on offer, their lengths and their minimum and maximum amounts are shown when you sign in.</p>
        <h2>Track your investment</h2>
        <p>See value, maturity date and performance in your dashboard.</p>
        <h2>Certificate issued</h2>
        <p>Receive a numbered investment certificate once your funds are confirmed.</p>
        <div class="button-row">
            <a href="{{ route('register') }}" class="button button--forest">Become an investor</a>
            <a href="{{ route('login') }}" class="button button--outline-dark">Sign in</a>
        </div>
        <p class="notice-fine">Commodity trading involves risk. Returns are a share of actual trading profit and are not guaranteed. Capital is at risk. Read the <a class="text-link" href="{{ route('public.risk') }}">risk notice</a>.</p>
    </section>
</x-site-layout>
