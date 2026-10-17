@props(['title', 'lead' => null, 'eyebrow' => null])
<section class="relative overflow-hidden bg-gradient-to-br from-brand-800 via-brand-700 to-brand-600 text-white">
    <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-gold-500/10 blur-3xl"></div>
    <div class="relative mx-auto max-w-7xl px-4 py-14 lg:py-20">
        @if($eyebrow)<p class="text-xs font-bold uppercase tracking-[0.25em] text-gold-500">{{ $eyebrow }}</p>@endif
        <h1 class="mt-2 max-w-3xl font-display text-3xl font-bold leading-tight lg:text-5xl">{{ $title }}</h1>
        @if($lead)<p class="mt-4 max-w-2xl text-lg leading-relaxed text-white/85">{{ $lead }}</p>@endif
        {{ $slot }}
    </div>
</section>
