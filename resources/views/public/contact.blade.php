@php $site = \App\Support\SiteContact::get(); @endphp
@push('scripts')<script defer src="{{ asset('site/js/enquiry-form.js') }}"></script>@endpush
<x-site-layout title="Contact" description="Contact AFISAB NIG. LTD about commodity supply, becoming a supplier, or any other question.">
    <section class="trade-banner trade-banner--form">
      <div class="wrap trade-banner-grid">
        <div>
          <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><span aria-current="page">Contact</span></nav>
          <p class="eyebrow">BUSINESS ENQUIRIES</p>
          <h1 class="display">Talk to<br><em>AFISAB.</em></h1>
          <p>Buying commodities in volume or looking for a market for your produce? Tell us what you need.</p>
        </div>
        <div class="trade-banner-aside"><strong>GENERAL ENQUIRIES</strong><p>For buyers and suppliers there are dedicated forms; use this one for anything else.</p></div>
      </div>
    </section>
    <section class="trade-enquiry" id="enquiry" aria-label="Contact">
      <div class="wrap enquiry-grid">
        <div class="enquiry-main">
          @include('public._enquiry-form', ['type' => 'general', 'commodities' => $commodities ?? []])
        </div>
        <aside class="enquiry-aside">
          <h2>How it works</h2>
          <ol><li>Send us your question or message.</li><li>A member of our team replies using the details you give.</li></ol>
          <a class="text-link" href="https://wa.me/{{ $site['whatsapp'] }}?text=Hello%20AFISAB%2C%20I%20have%20a%20question." target="_blank" rel="noopener noreferrer">Start a WhatsApp chat</a><br><a class="text-link" href="mailto:{{ $site['email'] }}">Email {{ $site['email'] }}</a><br><a class="text-link" href="{{ route('public.buyers') }}#enquiry">Request a quote</a><br><a class="text-link" href="{{ route('public.suppliers') }}#enquiry">Supply with AFISAB</a>
        </aside>
      </div>
    </section>
</x-site-layout>
