@php $img = asset('site/assets').'/images/'.$slug; $whatsapp = \App\Support\SiteContact::get()['whatsapp']; @endphp
<x-site-layout :title="$c['name']" :description="$c['summary'].' Request availability, specifications and a supply quote from AFISAB.'">
<div class="detail-page"><div class="wrap">
<nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><a href="{{ route('public.trade') }}">Commodities</a><span aria-hidden="true">/</span><span aria-current="page">{{ $c['name'] }}</span></nav>
      <div class="detail-grid">
        <div>
        <figure class="editorial-figure detail-photo trade-detail-illustration">
          <img src="{{ $img }}-720.webp" srcset="{{ $img }}-400.webp 400w, {{ $img }}-720.webp 720w" sizes="(min-width: 1320px) 620px, (min-width: 800px) 50vw, 94vw" width="720" height="480" alt="{{ $c['image_alt'] }}" decoding="async" fetchpriority="high">
          <figcaption><span>{{ $c['name'] }}</span><span>Illustrative commodity image</span></figcaption>
        </figure>
        @if($c['closeup'])
        <details class="trade-image-detail">
          <summary>{{ $c['closeup']['label'] }}</summary>
          <figure class="editorial-figure">
            <img src="{{ asset('site/assets') }}/images/{{ $c['closeup']['image'] }}-720.webp" srcset="{{ asset('site/assets') }}/images/{{ $c['closeup']['image'] }}-400.webp 400w, {{ asset('site/assets') }}/images/{{ $c['closeup']['image'] }}-720.webp 720w" sizes="(min-width: 1320px) 620px, (min-width: 800px) 50vw, 94vw" width="720" height="480" alt="{{ $c['closeup']['alt'] }}" loading="lazy" decoding="async">
            <figcaption>Illustrative close-up</figcaption>
          </figure>
        </details>
        @endif
        </div>
        <div class="detail-copy">
          <p class="eyebrow">{{ $c['category_label'] }}</p>
          <h1 class="display">{{ $c['name'] }}</h1>
          <p>{{ $c['summary'] }}</p>
          <dl class="detail-facts">
            <div><dt>Availability</dt><dd>{{ $c['availability'] }}</dd></div>
            <div><dt>Pricing</dt><dd>{{ $c['pricing'] }}</dd></div>
            <div><dt>Packaging</dt><dd>{{ $c['packaging_fact'] }}</dd></div>
          </dl>
          <div class="button-row">
            <a class="button button--forest" href="{{ route('public.buyers', ['commodity' => $c['db_name']]) }}#enquiry">Request a Quote<svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a>
            <a class="text-link" href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Hello AFISAB, I would like to enquire about '.$c['name'].'. Please confirm availability and supply terms.'."\n\n".'Required quantity and unit:'."\n".'Preferred specifications:'."\n".'Delivery destination:') }}" target="_blank" rel="noopener noreferrer">WhatsApp Enquiry<svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a>
          </div>
        </div>
      </div>
      <div class="detail-more">
        <section><h2>Quality specifications</h2><p>{{ $c['quality'] }}</p></section>
        <section><h2>Typical commercial applications</h2><ul>@foreach($c['applications'] as $a)<li>{{ $a }}</li>@endforeach</ul></section>
        <section><h2>Packaging and handling</h2><p>{{ $c['packaging'] }}</p></section>
        <section><h2>Sourcing and supply</h2><p>{{ $c['sourcing'] }}</p></section>
      </div>
      @if($related)
      <section class="trade-related"><h2>Related commodities</h2><div class="trade-related-links">@foreach($related as $rs => $rc)<a href="{{ route('public.commodity', $rs) }}">{{ $rc['name'] }}<svg class="icon icon--arrow" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></a>@endforeach</div></section>
      @endif
      <section class="trade-detail-cta">
        <div><h2>Discuss your {{ $c['name'] }} order</h2><p>Share your quantity, specifications and delivery destination.</p></div>
        <a class="button button--forest" href="{{ route('public.buyers', ['commodity' => $c['db_name']]) }}#enquiry">Request a Supply Quote<svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a>
      </section>
      <p><a class="text-link" href="{{ route('public.trade') }}">Back to Catalogue<svg class="icon icon--arrow" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></a></p>
</div></div>
</x-site-layout>
