<x-public-layout title="About" description="AFISAB NIG. LTD is a Nigerian agricultural commodity trading and supply company. Building Trust, Delivering Excellence.">
    <x-page-banner eyebrow="About us" title="AFISAB NIG. LTD" lead="{{ config('afisab.slogan') }}" />

    <section class="mx-auto grid max-w-7xl gap-12 px-4 py-14 lg:grid-cols-3">
        <div class="space-y-5 leading-relaxed text-gray-700 lg:col-span-2">
            <h2 class="font-display text-2xl font-bold text-brand-700">Who we are</h2>
            <p>AFISAB NIG. LTD is a Nigerian company in agricultural commodity trading and supply. Our business is the sourcing, aggregation, trading and supply of agricultural commodities.</p>
            <p>We buy from farmers, agricultural cooperatives, commodity aggregators and other suppliers, and we supply industrial buyers, manufacturers, feed mills, commodity merchants and other bulk purchasers.</p>

            <h2 class="pt-4 font-display text-2xl font-bold text-brand-700">Our commitments</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="rounded-xl border border-stone-200 p-6"><p class="font-display text-xl font-bold text-brand-700">Building Trust</p><p class="mt-2 text-sm text-gray-600">With the suppliers who sell to us and the buyers who rely on us, through clear terms, honest dealing and complete records.</p></div>
                <div class="rounded-xl border border-stone-200 p-6"><p class="font-display text-xl font-bold text-brand-700">Delivering Excellence</p><p class="mt-2 text-sm text-gray-600">In the quality of the commodities we supply and the discipline with which we buy, store and sell them.</p></div>
            </div>

            <h2 class="pt-4 font-display text-2xl font-bold text-brand-700">Markets</h2>
            <p>We serve Nigerian domestic markets. International markets are an expansion opportunity we intend to pursue.</p>
        </div>
        <aside class="h-fit rounded-2xl bg-stone-50 p-6">
            <h3 class="font-black text-brand-700">Company details</h3>
            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-xs font-bold uppercase tracking-wider text-gray-500">Registered name</dt><dd>AFISAB NIG. LTD</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-gray-500">Industry</dt><dd>Agricultural commodity trading and supply</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-gray-500">Country</dt><dd>Nigeria</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-gray-500">Email</dt><dd><a class="text-brand-700 underline" href="mailto:{{ config('afisab.contact.email') }}">{{ config('afisab.contact.email') }}</a></dd></div>
            </dl>
        </aside>
    </section>

    @include('public._contact-band')
</x-public-layout>
