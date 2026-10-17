<x-public-layout description="AFISAB NIG. LTD is a Nigerian agricultural commodity trading and supply company. We source from farmers, cooperatives and aggregators and supply industrial buyers, manufacturers, feed mills and commodity merchants.">
    <section class="relative overflow-hidden bg-gradient-to-br from-brand-800 via-brand-700 to-brand-600 text-white">
        <div class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-gold-500/15 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-40 -left-20 h-96 w-96 rounded-full bg-white/5 blur-3xl"></div>
        <div class="relative mx-auto max-w-7xl px-4 py-20 lg:py-28">
            <p class="text-xs font-bold uppercase tracking-[0.3em] text-gold-500">Agricultural commodity trading and supply</p>
            <h1 class="mt-4 max-w-4xl font-display text-4xl font-bold leading-tight lg:text-6xl">{{ config('afisab.slogan') }}</h1>
            <p class="mt-6 max-w-2xl text-lg leading-relaxed text-white/85">AFISAB NIG. LTD sources, aggregates, trades and supplies agricultural commodities in Nigeria, connecting farmers, cooperatives and aggregators with industrial buyers, manufacturers, feed mills and commodity merchants.</p>
            <div class="mt-9 flex flex-wrap gap-3">
                <a href="{{ route('public.buyers') }}#enquiry" class="inline-flex items-center gap-2 rounded-md bg-gradient-to-br from-gold-500 to-gold-600 px-6 py-3 font-bold text-brand-800 shadow-lg hover:brightness-110">Request a quote <x-icon name="arrow" class="h-4 w-4" /></a>
                <a href="{{ route('public.suppliers') }}#enquiry" class="inline-flex items-center gap-2 rounded-md border border-white/40 px-6 py-3 font-bold text-white hover:bg-white/10">Sell to us</a>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-16">
        <p class="text-xs font-bold uppercase tracking-[0.25em] text-gold-600">What we do</p>
        <h2 class="mt-2 max-w-2xl font-display text-3xl font-bold text-brand-700">From the farm gate to the factory floor</h2>
        <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            @foreach([
                ['search', 'Sourcing', 'We buy from farmers, agricultural cooperatives, commodity aggregators and other suppliers.'],
                ['layers', 'Aggregation', 'We consolidate supply into the volumes that industrial and bulk buyers need.'],
                ['scale', 'Trading', 'We trade agricultural commodities in Nigerian domestic markets.'],
                ['truck', 'Supply', 'We supply manufacturers, feed mills, commodity merchants and other bulk purchasers.'],
            ] as [$icon, $title, $text])
                <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                    <span class="grid h-12 w-12 place-items-center rounded-lg bg-brand-50 text-brand-700"><x-icon :name="$icon" /></span>
                    <h3 class="mt-4 text-lg font-black text-brand-700">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $text }}</p>
                </div>
            @endforeach
        </div>
        <p class="mt-8"><a class="inline-flex items-center gap-2 font-bold text-brand-700 hover:underline" href="{{ route('public.how') }}">How we work <x-icon name="arrow" class="h-4 w-4" /></a></p>
    </section>

    <section class="bg-stone-50">
        <div class="mx-auto max-w-7xl px-4 py-16">
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-gold-600">Our commodities</p>
            <h2 class="mt-2 max-w-2xl font-display text-3xl font-bold text-brand-700">A broad portfolio of Nigerian agricultural commodities</h2>
            <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach($groups as $group)
                    <div class="rounded-xl border border-stone-200 bg-white p-6">
                        <h3 class="font-black text-brand-700">{{ $group['label'] }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-gray-600">{{ implode(', ', $group['items']) }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-8"><a class="inline-flex items-center gap-2 font-bold text-brand-700 hover:underline" href="{{ route('public.trade') }}">See everything we trade <x-icon name="arrow" class="h-4 w-4" /></a></p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-16">
        <p class="text-xs font-bold uppercase tracking-[0.25em] text-gold-600">Why AFISAB</p>
        <h2 class="mt-2 max-w-2xl font-display text-3xl font-bold text-brand-700">Discipline in how commodities are bought, held and sold</h2>
        <div class="mt-10 grid gap-6 md:grid-cols-3">
            @foreach([
                ['shield', 'Quality-checked stock', 'Goods are checked when they are received. Moisture and grade are recorded for every lot, and stock that fails the check is not sold.'],
                ['box', 'Recorded and traceable', 'Every lot is recorded from the supplier it was bought from, through our warehouse, to the buyer it was sold to.'],
                ['check', 'Controlled procurement', 'Purchases are proposed, approved and paid under separate sign-off, so no single person commits our money alone.'],
            ] as [$icon, $title, $text])
                <div class="flex gap-4">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-700 text-white"><x-icon :name="$icon" class="h-5 w-5" /></span>
                    <div><h3 class="font-black text-brand-700">{{ $title }}</h3><p class="mt-1 text-sm leading-relaxed text-gray-600">{{ $text }}</p></div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="bg-stone-50">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-16 md:grid-cols-2">
            <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <h3 class="font-display text-2xl font-bold text-brand-700">For buyers</h3>
                <p class="mt-3 text-sm leading-relaxed text-gray-600">Industrial buyers, manufacturers, feed mills, commodity merchants and other bulk purchasers: tell us what you need and we will respond with availability and terms.</p>
                <a href="{{ route('public.buyers') }}" class="mt-5 inline-flex items-center gap-2 font-bold text-brand-700 hover:underline">Supply to your business <x-icon name="arrow" class="h-4 w-4" /></a>
            </div>
            <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-stone-200">
                <h3 class="font-display text-2xl font-bold text-brand-700">For suppliers</h3>
                <p class="mt-3 text-sm leading-relaxed text-gray-600">Farmers, agricultural cooperatives and commodity aggregators: if you have produce to sell, register your interest and our procurement team will contact you.</p>
                <a href="{{ route('public.suppliers') }}" class="mt-5 inline-flex items-center gap-2 font-bold text-brand-700 hover:underline">Sell your produce <x-icon name="arrow" class="h-4 w-4" /></a>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-16">
        <div class="flex items-start gap-4">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700"><x-icon name="globe" /></span>
            <div>
                <h2 class="font-display text-2xl font-bold text-brand-700">Markets</h2>
                <p class="mt-2 max-w-3xl leading-relaxed text-gray-600">We operate in Nigerian domestic markets today. International markets are an opportunity we see for future expansion.</p>
            </div>
        </div>
    </section>

    @include('public._contact-band')
</x-public-layout>
