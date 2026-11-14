@props(['title' => null, 'description' => null, 'compact' => false])
@php
    extract(\App\Support\SiteContact::get());
    $footerCommodities = \App\Support\SiteCommodities::featured();
    $fullTitle = $title ? $title.' | '.$company : $company.' | Agricultural Commodity Trading & Supply';
    $description = $description ?: 'AFISAB sources, aggregates and supplies agricultural commodities for processors, manufacturers and commercial buyers in Nigeria. Explore our commodities and request a supply quote.';
    $organization = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $company,
        'slogan' => rtrim(config('afisab.slogan'), '.'),
        'url' => url('/'),
        'email' => $email,
        'telephone' => $phone,
        'areaServed' => 'NG',
        'description' => 'Agricultural commodity sourcing, aggregation, trading and supply.',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#084d32">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $company }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary">
    <link rel="icon" type="image/png" href="{{ asset('image/favicon.png') }}">
    <link rel="preload" href="{{ asset('site/assets/fonts/manrope-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('site/css/system.css') }}">
    <link rel="stylesheet" href="{{ asset('site/css/pages.css') }}">
    <link rel="stylesheet" href="{{ asset('site/css/trade.css') }}">
    <link rel="stylesheet" href="{{ asset('site/css/additions.css') }}">
    <script type="application/ld+json">{!! json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    <script defer src="{{ asset('site/js/navigation.js') }}"></script>
    @stack('scripts')
</head>
<body id="top">
  <a class="skip-link" href="#main-content">Skip to content</a>
  <div class="utility"><div class="wrap utility-inner">
    <span class="utility-origin"><span></span> Nigerian agricultural commodity trade</span>
    <div class="utility-contact"><a href="mailto:{{ $email }}"><svg class="icon icon--mail" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>{{ $email }}</a><a href="tel:{{ $phone }}"><svg class="icon icon--phone" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4c0 2-2 3-4 3A17 17 0 0 1 3 7c0-2 1-4 3-4Z"/></svg>{{ $phoneShown }}</a></div>
  </div></div>
  <header class="site-header" data-header>
    <div class="wrap header-inner">
      <a class="brand" href="{{ route('home') }}" aria-label="AFISAB NIG. LTD home">
  <img class="brand-mark" src="{{ asset('image/logo-mark.png') }}" width="176" height="176" alt="">
  <span class="brand-type">AFISAB <span>NIG. LTD</span></span>
</a>
      <button type="button" class="menu-toggle" aria-expanded="false" aria-controls="primary-nav" hidden><span>Menu</span><svg class="icon icon--menu" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg><svg class="icon icon--close" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 5 14 14M19 5 5 19"/></svg></button>
      <nav id="primary-nav" class="primary-nav" aria-label="Main navigation">
        <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
        <a href="{{ $home }}#about">About Us</a>
        <a href="{{ route('public.trade') }}" @if(request()->routeIs('public.trade', 'public.commodity')) aria-current="page" @endif>Commodities</a>
        <details class="nav-group">
          <summary>What We Do <svg class="icon icon--down" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
          <div class="nav-dropdown">
            <a href="{{ $home }}#capabilities">Our Capabilities</a>
            <a href="{{ $home }}#quality">Quality Assurance</a>
            <a href="{{ $home }}#process">Our Process</a>
          </div>
        </details>
        <details class="nav-group">
          <summary>Work With Us <svg class="icon icon--down" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
          <div class="nav-dropdown">
            <a href="{{ route('public.buyers') }}#enquiry">For Buyers</a>
            <a href="{{ route('public.suppliers') }}#enquiry">For Suppliers</a>
          </div>
        </details>
        <a href="{{ route('public.contact') }}" @if(request()->routeIs('public.contact')) aria-current="page" @endif>Contact</a>
        @if(config('afisab.public_investor_page'))<a href="{{ route('public.investors') }}">Investors</a>@endif
        <a class="nav-investor" href="{{ route('login') }}"><svg class="icon icon--lock" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg>Investor Portal</a>
        <a class="button button--primary nav-mobile-quote" href="{{ route('public.buyers') }}#enquiry">Request a Quote<svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a>
      </nav>
      <a class="button button--primary header-quote" href="{{ route('public.buyers') }}#enquiry">Request a Quote<svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a>
    </div>
  </header>
  <main id="main-content">{{ $slot }}</main>
  <footer class="site-footer {{ $compact ? 'site-footer--compact' : '' }}" @unless($compact) id="contact" @endunless>
    <div class="wrap">
      @unless($compact)
      <div class="footer-lead">
        <div><p class="eyebrow">GET IN TOUCH</p><h2>Let’s talk <em>trade.</em></h2></div>
        <a class="button button--outline-light" href="mailto:{{ $email }}">Email {{ $short }}<svg class="icon icon--mail" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></a>
      </div>
      @endunless
      <div class="footer-grid">
        <div class="footer-brand">
          <a class="brand" href="{{ route('home') }}" aria-label="AFISAB NIG. LTD home">
  <img class="brand-mark" src="{{ asset('image/logo-mark.png') }}" width="176" height="176" alt="">
  <span class="brand-type">AFISAB <span>NIG. LTD</span></span>
</a>
          <p>Connecting Nigerian agricultural producers with commercial markets.</p>
          <p class="footer-tagline">Building Trust, Delivering Excellence.</p>
        </div>
        <div><h3>Company</h3><ul>
          <li><a href="{{ $home }}#about">About Us</a></li>
          <li><a href="{{ $home }}#capabilities">Our Capabilities</a></li>
          <li><a href="{{ $home }}#quality">Quality Assurance</a></li>
          <li><a href="{{ $home }}#vision">Our Vision</a></li>
          <li><a href="{{ route('login') }}">Investor Portal</a></li>
        </ul></div>
        <div><h3>Commodities</h3><ul>@foreach($footerCommodities as $slug => $name)<li><a href="{{ route('public.commodity', $slug) }}">{{ $name }}</a></li>@endforeach<li><a href="{{ route('public.trade') }}">All commodities</a></li></ul></div>
        <div><h3>Business Enquiries</h3><ul class="footer-contacts">
          <li><a href="{{ route('public.buyers') }}#enquiry">For Buyers</a></li>
          <li><a href="{{ route('public.suppliers') }}#enquiry">For Suppliers</a></li>
          @foreach($phones as $p)<li><a href="tel:{{ preg_replace('/[^+0-9]/', '', $p) }}"><svg class="icon icon--phone" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4c0 2-2 3-4 3A17 17 0 0 1 3 7c0-2 1-4 3-4Z"/></svg>{{ $p }}</a></li>@endforeach
          <li><a href="mailto:{{ $email }}"><svg class="icon icon--mail" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>{{ $email }}</a></li>
          <li><a href="https://wa.me/{{ $whatsapp }}" rel="noopener"><svg class="icon icon--chat" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-8 8 8.6 8.6 0 0 1-3.6-.8L4 20l1.3-4.3A8 8 0 1 1 20 11.5Z"/><path d="M8.5 11.5h.01m3.49 0h.01m3.49 0h.01"/></svg>WhatsApp</a></li>
          <li><span class="footer-location"><svg class="icon icon--pin" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.4"/></svg>{{ $location }}</span></li>
        </ul></div>
      </div>
      <div class="footer-bottom">
        <p>© {{ date('Y') }} {{ $company }}</p>
        <div><a href="{{ route('public.privacy') }}">Privacy</a><a href="{{ route('public.terms') }}">Terms</a><a href="{{ route('public.risk') }}">Risk notice</a><a href="{{ route('verify.form') }}">Verify a certificate</a><a href="{{ $home }}#top">Back to top ↑</a></div>
      </div>
    </div>
  </footer>
</body>
</html>
