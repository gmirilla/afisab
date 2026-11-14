@php extract(\App\Support\SiteContact::get()); $featured = \App\Support\SiteCommodities::featuredItems(); @endphp
@push('scripts')<script defer src="{{ asset('site/js/hero-carousel.js') }}"></script>@endpush
<x-site-layout compact><section class="hero" aria-labelledby="hero-title" data-hero>
      <div class="hero-images" aria-hidden="true">
        <img class="hero-photo is-active" data-slide data-caption="Agricultural production" src="{{ asset('site/assets') }}/images/hero-1536.webp" srcset="{{ asset('site/assets') }}/images/hero-640.webp 640w, {{ asset('site/assets') }}/images/hero-1024.webp 1024w, {{ asset('site/assets') }}/images/hero-1536.webp 1536w" sizes="100vw" width="1536" height="1024" alt="" decoding="async" fetchpriority="high">
        <img class="hero-photo hero-photo--handling" data-slide data-caption="Aggregation and storage" src="{{ asset('site/assets') }}/images/hero-handling-1280.webp" srcset="{{ asset('site/assets') }}/images/hero-handling-640.webp 640w, {{ asset('site/assets') }}/images/hero-handling-1280.webp 1280w" sizes="100vw" width="1280" height="853" alt="" decoding="async" fetchpriority="low">
        <img class="hero-photo hero-photo--commodities" data-slide data-caption="Sesame, soybeans and shea nuts" src="{{ asset('site/assets') }}/images/hero-commodities-1280.webp" srcset="{{ asset('site/assets') }}/images/hero-commodities-640.webp 640w, {{ asset('site/assets') }}/images/hero-commodities-1280.webp 1280w" sizes="100vw" width="1280" height="853" alt="" decoding="async" fetchpriority="low">
      </div>
      <div class="wrap hero-inner">
        <div class="hero-copy">
          <p class="eyebrow">AFISAB NIG. LTD <span aria-hidden="true">/</span> COMMODITY TRADING</p>
          <h1 id="hero-title">Building Trust,<br> <em>Delivering Excellence.</em></h1>
          <p>We source, aggregate and supply Nigerian agricultural commodities for commercial buyers in Nigeria.</p>
          <div class="button-row">
            <a class="button button--primary" href="{{ route('public.trade') }}">Explore Commodities<svg class="icon icon--arrow" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></a>
            <a class="button button--outline-light" href="{{ route('public.buyers') }}#enquiry">Request a Quote<svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a>
          </div>
        </div>
        <div class="hero-bottom">
          <a class="hero-scroll" href="#about"><svg class="icon icon--scroll" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v15m-6-6 6 6 6-6"/></svg> Discover AFISAB</a>
          <div class="hero-controls" aria-label="Banner controls" data-hero-controls hidden>
            <span class="hero-caption" data-hero-caption aria-live="off" aria-atomic="true">Agricultural production</span>
            <div class="hero-control-buttons">
              <button type="button" data-hero-action="previous" aria-label="Previous image"><svg class="icon icon--previous" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg></button>
              <button type="button" data-hero-action="toggle" aria-label="Pause image rotation"><svg class="icon icon--pause" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5v14m6-14v14"/></svg><svg class="icon icon--play" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m8 5 12 7-12 7Z"/></svg></button>
              <button type="button" data-hero-action="next" aria-label="Next image"><svg class="icon icon--next" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m10 6 6 6-6 6"/></svg></button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="pillars">
      <ul class="wrap pillars-inner" aria-label="Our focus">
        <li><span class="pillar-icon"><svg class="icon icon--leaf" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 4C10 4 5 8 5 14a5 5 0 0 0 5 5c6 0 10-5 10-15ZM4 20l10-10"/></svg></span><div><h2>Nigerian Sourcing</h2><p>Connecting producers with commercial buyers.</p></div></li><li><span class="pillar-icon"><svg class="icon icon--shield" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s7-3.5 7-10V5l-7-3-7 3v7c0 6.5 7 10 7 10Z"/><path d="m9 12 2 2 4-4"/></svg></span><div><h2>Quality Assurance</h2><p>Inspection, grading and careful handling.</p></div></li><li><span class="pillar-icon"><svg class="icon icon--globe" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/></svg></span><div><h2>Coordinated Supply</h2><p>Support from aggregation through delivery.</p></div></li>
      </ul>
    </div>

    <section id="about" class="section section--about">
      <div class="wrap about-grid">
        <figure class="editorial-figure">
          <img src="{{ asset('site/assets') }}/images/inspection-960.webp" srcset="{{ asset('site/assets') }}/images/inspection-480.webp 480w, {{ asset('site/assets') }}/images/inspection-960.webp 960w" sizes="(min-width: 1320px) 580px, (min-width: 800px) 44vw, 94vw" width="960" height="640" alt="Hands holding a sample of golden maize kernels above a grain sack" decoding="async" loading="lazy">
          <figcaption><span>01 / At origin</span><span>Commodity inspection · Illustrative</span></figcaption>
        </figure>
        <div class="about-copy">
          <p class="eyebrow">WHO WE ARE</p>
          <h2 class="display">Rooted in agriculture.<br><em>Driven by excellence.</em></h2>
          <p>AFISAB NIG. LTD is a Nigerian agricultural commodity trading and supply company. We connect farming communities and producers with commercial buyers in Nigeria, with international markets as a future opportunity.</p>
          <p>From sourcing and aggregation to quality checks, storage and delivery coordination, we help buyers arrange supply around the volumes and specifications they need.</p>
          <a class="text-link" href="#capabilities">Explore Our Capabilities<svg class="icon icon--arrow" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></a>
        </div>
      </div>
    </section>

    <section id="commodities" class="section section--commodities">
      <div class="wrap">
        <div class="section-head">
          <div><p class="eyebrow">OUR COMMODITIES</p><h2 class="display">The commodities<br><em>we trade.</em></h2></div>
          <p>Explore our featured commodities for commercial buyers. Ask us about current availability, specifications and supply terms.</p>
        </div>
        <div class="commodities-grid">@foreach($featured as $slug => $c)@include('public._commodity-card', ['slug' => $slug, 'c' => $c])@endforeach</div>
        <p class="featured-catalogue"><a class="text-link" href="{{ route('public.trade') }}">Browse the Full Catalogue <svg class="icon icon--arrow" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></a></p>
      </div>
    </section>

    <section id="capabilities" class="section section--capabilities">
      <div class="wrap capabilities-grid">
        <div class="capabilities-copy">
          <p class="eyebrow">OUR CAPABILITIES</p>
          <h2 class="display">From farm gate<br><em>to buyer.</em></h2>
          <p>We coordinate each stage of supply around the buyer’s volume, specifications and delivery needs.</p>
          <div class="services">
            <details class="service" open><summary><span class="service-index">01</span><span>Commodity sourcing</span><svg class="icon icon--plus" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-7-7v14"/></svg></summary><p>Source against buyer requirements through relationships with agricultural producers and suppliers.</p></details><details class="service"><summary><span class="service-index">02</span><span>Aggregation & procurement</span><svg class="icon icon--plus" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-7-7v14"/></svg></summary><p>Bring volumes together from producers and aggregators to meet the needs of commercial orders.</p></details><details class="service"><summary><span class="service-index">03</span><span>Quality assurance</span><svg class="icon icon--plus" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-7-7v14"/></svg></summary><p>Inspect and grade commodities against agreed specifications, with sorting and cleaning where required.</p></details><details class="service"><summary><span class="service-index">04</span><span>Warehousing & storage</span><svg class="icon icon--plus" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-7-7v14"/></svg></summary><p>Coordinate storage and packaging with commodity condition and buyer requirements in mind.</p></details><details class="service"><summary><span class="service-index">05</span><span>Logistics coordination</span><svg class="icon icon--plus" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-7-7v14"/></svg></summary><p>Plan movement and delivery around the agreed destination, timing and handling needs.</p></details><details class="service"><summary><span class="service-index">06</span><span>Buyer support</span><svg class="icon icon--plus" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-7-7v14"/></svg></summary><p>Clear communication on availability, specifications and delivery for each order, from first enquiry to handover.</p></details>
          </div>
        </div>
        <figure class="editorial-figure capability-figure">
          <img src="{{ asset('site/assets') }}/images/warehouse-900.webp" srcset="{{ asset('site/assets') }}/images/warehouse-480.webp 480w, {{ asset('site/assets') }}/images/warehouse-900.webp 900w" sizes="(min-width: 1320px) 520px, (min-width: 800px) 42vw, 94vw" width="900" height="1200" alt="An aisle between stacked sacks of grain in an agricultural warehouse" decoding="async" loading="lazy">
          <figcaption><span>02 / In storage</span><span>Commodity warehousing · Illustrative</span></figcaption>
        </figure>
      </div>
    </section>

    <section id="quality" class="section section--quality">
      <div class="wrap quality-grid">
        <div><p class="eyebrow">WHY AFISAB</p><h2 class="display">Confidence at every stage <em>of the supply chain.</em></h2></div>
        <div>
          <p>We start with the buyer’s requirements and carry them through sourcing, inspection and handling. We confirm the details that matter for each order, including product condition, packaging and delivery.</p>
          <ul class="quality-list">
            <li><svg class="icon icon--check" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Structured procurement</li><li><svg class="icon icon--check" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Quality-focused handling</li><li><svg class="icon icon--check" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Producer relationships</li><li><svg class="icon icon--check" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Delivery coordination</li><li><svg class="icon icon--check" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Buyer-specific requirements</li><li><svg class="icon icon--check" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Clear communication</li>
          </ul>
          <a class="text-link" href="mailto:info@afisab.com?subject=Commodity%20specifications%20enquiry">Request Product Specifications<svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a>
        </div>
      </div>
    </section>

    <section id="process" class="section section--process">
      <div class="wrap">
        <div class="section-head">
          <div><p class="eyebrow">HOW WE WORK</p><h2 class="display">From harvest <em>to handover.</em></h2></div>
          <p>Six stages connect agricultural producers with commercial buyers.</p>
        </div>
        <ol class="process-grid">
          <li><span class="process-index" aria-hidden="true">01</span><h3>Sourcing</h3><p>Identify producers and supply options.</p></li><li><span class="process-index" aria-hidden="true">02</span><h3>Aggregation</h3><p>Bring together the required volume.</p></li><li><span class="process-index" aria-hidden="true">03</span><h3>Inspection & grading</h3><p>Check against agreed specifications.</p></li><li><span class="process-index" aria-hidden="true">04</span><h3>Storage & packaging</h3><p>Prepare and protect the commodity.</p></li><li><span class="process-index" aria-hidden="true">05</span><h3>Logistics coordination</h3><p>Arrange movement to destination.</p></li><li><span class="process-index" aria-hidden="true">06</span><h3>Buyer delivery</h3><p>Coordinate handover to the buyer.</p></li>
        </ol>
      </div>
    </section>

    <section id="buyers" class="section section--buyers">
      <div class="wrap buyers-grid">
        <div><p class="eyebrow">FOR COMMERCIAL BUYERS</p><h2 class="display">Looking for a reliable<br><em>commodity supplier?</em></h2></div>
        <div><p>Tell us what you need: the commodity, volume, preferred specifications and delivery destination. We’ll use those details to discuss availability and prepare a quote.</p>
          <div class="button-row"><a class="button button--primary" href="{{ route('public.buyers') }}#enquiry">Request a Supply Quote<svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a><a class="text-link" href="https://wa.me/2348057520669?text=Hello%20AFISAB%2C%20I%20would%20like%20to%20request%20a%20supply%20quotation.%0A%0ACommodity%3A%0ARequired%20quantity%20and%20unit%3A%0APreferred%20specifications%3A%0ADelivery%20destination%3A%0ARequired%20delivery%20date%3A">WhatsApp<svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a></div>
        </div>
      </div>
    </section>

    <div class="section section--future">
      <div class="wrap future-grid">
        <section id="suppliers" class="supplier-panel">
          <div class="panel-icon"><svg class="icon icon--leaf" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 4C10 4 5 8 5 14a5 5 0 0 0 5 5c6 0 10-5 10-15ZM4 20l10-10"/></svg></div>
          <p class="eyebrow">FOR FARMERS &amp; AGGREGATORS</p>
          <h2 class="display">Grow with <em>AFISAB.</em></h2>
          <p>We welcome enquiries from farmers, cooperatives, aggregators and commodity producers. Tell us what you can supply and when it will be ready.</p>
          <a class="text-link" href="{{ route('public.suppliers') }}#enquiry">Become a Supply Partner<svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a>
        </section>
        <section id="vision" class="vision-panel">
          <div class="panel-icon"><svg class="icon icon--globe" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/></svg></div>
          <p class="eyebrow">OUR VISION</p>
          <h2 class="display">A long view <em>of trade.</em></h2>
          <p>Our ambition is to be a trusted partner in Nigerian agricultural commodity trade. We aim to deepen sourcing relationships and widen access to commercial markets.</p>
          <a class="text-link" href="mailto:{{ $email }}?subject=Strategic%20partnership%20enquiry">Discuss Strategic Partnerships<svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a>
        </section>
      </div>
    </div>
    <section class="section section--contact" id="contact" aria-labelledby="contact-title">
      <div class="wrap contact-grid">
        <div class="contact-intro">
          <p class="eyebrow">BUSINESS ENQUIRIES</p>
          <h2 class="display" id="contact-title">Talk to <em>AFISAB.</em></h2>
          <p>Buying commodities in volume or looking for a market for your produce? Tell us what you need.</p>
        </div>
        <ul class="contact-channels">
          <li><a class="contact-channel" href="mailto:{{ $email }}"><span class="contact-icon"><svg class="icon icon--mail" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span><span><strong>Email</strong><span>{{ $email }}</span></span><svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a></li>
          <li><a class="contact-channel" href="tel:{{ $phone }}"><span class="contact-icon"><svg class="icon icon--phone" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4c0 2-2 3-4 3A17 17 0 0 1 3 7c0-2 1-4 3-4Z"/></svg></span><span><strong>Call</strong><span>{{ $phoneShown }}</span></span><svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a></li>
          <li><a class="contact-channel" href="https://wa.me/{{ $whatsapp }}" rel="noopener"><span class="contact-icon"><svg class="icon icon--chat" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-8 8 8.6 8.6 0 0 1-3.6-.8L4 20l1.3-4.3A8 8 0 1 1 20 11.5Z"/><path d="M8.5 11.5h.01m3.49 0h.01m3.49 0h.01"/></svg></span><span><strong>WhatsApp</strong><span>Message us</span></span><svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a></li>
          <li><div class="contact-channel"><span class="contact-icon"><svg class="icon icon--pin" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.4"/></svg></span><span><strong>Location</strong><span>{{ $location }}</span></span></div></li>
        </ul>
      </div>
    </section>
</x-site-layout>
