@props(['title' => null, 'description' => null])
@php
    $company = config('afisab.company_name');
    $phone = config('afisab.contact.phone');
    $phoneShown = preg_replace('/^\+234(\d{3})(\d{3})(\d{4})$/', '+234 $1 $2 $3', $phone);
    $fullTitle = $title ? $title.' · '.$company : $company.' · Agricultural commodity trading and supply in Nigeria';
    $description = $description ?: 'AFISAB NIG. LTD sources, aggregates, trades and supplies agricultural commodities to industrial buyers, manufacturers, feed mills and commodity merchants in Nigeria.';
    $links = [
        ['What we trade', 'public.trade'],
        ['For buyers', 'public.buyers'],
        ['For suppliers', 'public.suppliers'],
        ['How we work', 'public.how'],
        ['About', 'public.about'],
        ['Contact', 'public.contact'],
    ];
    $organization = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $company,
        'slogan' => rtrim(config('afisab.slogan'), '.'),
        'url' => url('/'),
        'email' => config('afisab.contact.email'),
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
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=lato:400,700,900|playfair-display:600,700" rel="stylesheet">
    <script type="application/ld+json">{!! json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-gray-800 antialiased">
<header class="sticky top-0 z-40 border-b border-gold-500/40 bg-gradient-to-r from-brand-800 via-brand-700 to-brand-800 text-white shadow-lg shadow-brand-800/20">
    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-2.5 lg:gap-6">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white p-1 shadow-inner ring-1 ring-gold-500/60"><img src="{{ asset('image/logo-mark.png') }}" alt="AFISAB NIG. LTD logo" width="40" height="40" class="h-full w-full object-contain"></span>
            <span class="leading-tight">
                <font class="font-display text-lg font-bold tracking-wide">AFISAB Nig. Ltd</font>
            </span>
        </a>
        <button type="button" data-nav-toggle aria-expanded="false" aria-label="Menu" class="ml-auto rounded-md p-2 hover:bg-white/10 lg:hidden">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <div data-nav class="nav-panel flex-1 flex-col gap-1 max-lg:absolute max-lg:inset-x-0 max-lg:top-full max-lg:max-h-[80vh] max-lg:overflow-y-auto max-lg:bg-brand-800 max-lg:p-4 max-lg:shadow-xl lg:flex-row lg:items-center lg:gap-1">
            <nav class="flex flex-1 flex-col gap-1 lg:flex-row lg:items-center lg:justify-center">
                @foreach($links as [$label, $route])
                    <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($route) ? 'is-active' : '' }}">{{ $label }}</a>
                @endforeach
                @if(config('afisab.public_investor_page'))
                    <a href="{{ route('public.investors') }}" class="nav-link {{ request()->routeIs('public.investors') ? 'is-active' : '' }}">Investors</a>
                @endif
            </nav>
            <div class="flex flex-col gap-2 max-lg:mt-3 max-lg:border-t max-lg:border-white/10 max-lg:pt-3 lg:flex-row lg:items-center">
                <a href="{{ route('login') }}" class="nav-link">Investor portal</a>
                <a href="{{ route('public.buyers') }}#enquiry" class="rounded-md bg-gradient-to-br from-gold-500 to-gold-600 px-4 py-2 text-center text-sm font-bold text-brand-800 shadow hover:brightness-110">Request a quote</a>
            </div>
        </div>
    </div>
</header>

<main>{{ $slot }}</main>

<footer class="bg-brand-800 text-white/80">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 md:grid-cols-4">
        <div class="md:col-span-2">
            <div class="flex items-center gap-3">
                <span class="grid h-14 w-14 shrink-0 place-items-center rounded-xl bg-white p-1.5 ring-1 ring-gold-500/60"><img src="{{ asset('image/logo-mark.png') }}" alt="" width="56" height="56" class="h-full w-full object-contain"></span>
                <p class="font-display text-xl font-bold text-white">{{ $company }}</p>
            </div>
            <p class="mt-1 text-sm font-bold text-gold-500">{{ config('afisab.slogan') }}</p>
            <p class="mt-3 max-w-md text-sm leading-relaxed">Agricultural commodity sourcing, aggregation, trading and supply in Nigeria, for industrial buyers, manufacturers, feed mills and commodity merchants.</p>
        </div>
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-white">Explore</p>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach($links as [$label, $route])<li><a class="hover:text-white hover:underline" href="{{ route($route) }}">{{ $label }}</a></li>@endforeach
            </ul>
        </div>
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-white">Contact</p>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a class="hover:text-white hover:underline" href="mailto:{{ config('afisab.contact.email') }}">{{ config('afisab.contact.email') }}</a></li>
                <li><a class="hover:text-white hover:underline" href="tel:{{ $phone }}">{{ $phoneShown }}</a></li>
                <li><a class="hover:text-white hover:underline" href="https://wa.me/{{ ltrim($phone, '+') }}" rel="noopener">WhatsApp</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs">
            <p>© {{ date('Y') }} {{ $company }}. All rights reserved.</p>
            <p class="space-x-4"><a class="hover:text-white hover:underline" href="{{ route('public.privacy') }}">Privacy</a><a class="hover:text-white hover:underline" href="{{ route('public.terms') }}">Terms</a><a class="hover:text-white hover:underline" href="{{ route('public.risk') }}">Risk notice</a><a class="hover:text-white hover:underline" href="{{ route('verify.form') }}">Verify a certificate</a></p>
        </div>
    </div>
</footer>
</body>
</html>
