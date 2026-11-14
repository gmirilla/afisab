@php $site = \App\Support\SiteContact::get(); @endphp
@push('scripts')<script defer src="{{ asset('site/js/enquiry-form.js') }}"></script>@endpush
<x-site-layout title="For Suppliers" description="Tell AFISAB what you grow or aggregate, where it is located and when it will be ready.">
    <section class="trade-banner trade-banner--form">
      <div class="wrap trade-banner-grid">
        <div>
          <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><span aria-current="page">For Suppliers</span></nav>
          <p class="eyebrow">FOR FARMERS, COOPERATIVES &amp; AGGREGATORS</p>
          <h1 class="display">Supply with<br><em>AFISAB.</em></h1>
          <p>Tell us what you grow or aggregate, where it’s located and when it will be ready. We welcome enquiries from farmers, cooperatives and commodity suppliers.</p>
        </div>
        <div class="trade-banner-aside"><strong>SUPPLIER ENQUIRIES</strong><p>For producers and aggregators who want to discuss a one-off or recurring supply relationship.</p></div>
      </div>
    </section>
    <section class="trade-enquiry" id="enquiry" aria-label="For Suppliers">
      <div class="wrap enquiry-grid">
        <div class="enquiry-main">
          @include('public._enquiry-form', ['type' => 'supplier', 'commodities' => $commodities ?? []])
        </div>
        <aside class="enquiry-aside">
          <h2>How it works</h2>
          <ol><li>Share your commodity, location, estimated quantity and harvest or readiness status.</li><li>AFISAB will discuss the product condition and any specifications or handling needs.</li><li>If there is a fit, discuss timing, inspection and collection or delivery arrangements.</li></ol>
          <a class="text-link" href="https://wa.me/{{ $site['whatsapp'] }}?text=Hello%20AFISAB%2C%20I%20would%20like%20to%20discuss%20becoming%20a%20supply%20partner." target="_blank" rel="noopener noreferrer">Start a WhatsApp chat</a>
        </aside>
      </div>
    </section>
</x-site-layout>
