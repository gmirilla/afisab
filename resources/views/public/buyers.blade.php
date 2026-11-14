@php $site = \App\Support\SiteContact::get(); @endphp
@push('scripts')<script defer src="{{ asset('site/js/enquiry-form.js') }}"></script>@endpush
<x-site-layout title="Request a Quote" description="Tell us the commodity, quantity and delivery destination you need. AFISAB will discuss availability, specifications and supply terms.">
    <section class="trade-banner trade-banner--form">
      <div class="wrap trade-banner-grid">
        <div>
          <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><span aria-current="page">Request a Quote</span></nav>
          <p class="eyebrow">FOR COMMERCIAL BUYERS</p>
          <h1 class="display">Request a<br><em>supply quote.</em></h1>
          <p>Tell us what you need, how much and where it should go. We’ll use those details to discuss availability, specifications and supply terms.</p>
        </div>
        <div class="trade-banner-aside"><strong>BUYER ENQUIRIES</strong><p>For processors, feed mills, manufacturers and institutional procurement teams.</p></div>
      </div>
    </section>
    <section class="trade-enquiry" id="enquiry" aria-label="Request a Quote">
      <div class="wrap enquiry-grid">
        <div class="enquiry-main">
          @include('public._enquiry-form', ['type' => 'quote', 'commodities' => $commodities ?? []])
        </div>
        <aside class="enquiry-aside">
          <h2>How it works</h2>
          <ol><li>Share the commodity, required quantity, destination and quality requirements.</li><li>AFISAB confirms whether the quantity and specifications can be met.</li><li>Discuss the quotation, packaging, lead time and delivery arrangements.</li></ol>
          <a class="text-link" href="https://wa.me/{{ $site['whatsapp'] }}?text=Hello%20AFISAB%2C%20I%20would%20like%20to%20discuss%20a%20commodity%20supply%20quote." target="_blank" rel="noopener noreferrer">Start a WhatsApp chat</a><br><a class="text-link" href="{{ route('public.trade') }}">Browse Commodities</a>
        </aside>
      </div>
    </section>
</x-site-layout>
