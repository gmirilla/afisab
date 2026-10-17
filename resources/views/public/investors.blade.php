<x-public-layout title="Investors" description="Participate in AFISAB NIG. LTD's agricultural commodity trading programme as a registered investor.">
    <x-page-banner eyebrow="Investors" title="The AFISAB investor programme" lead="Participate in Nigeria's agricultural commodity trade through professionally managed, quality-assured trading cycles." />
    <section class="mx-auto max-w-5xl px-4 py-14">
        <div class="grid gap-6 md:grid-cols-3">
            <x-card><h3 class="font-black text-brand-700">Minimum ₦500,000</h3><p class="mt-2 text-sm text-gray-600">Choose a 3-month, 6-month or 12-month plan.</p></x-card>
            <x-card><h3 class="font-black text-brand-700">Track your investment</h3><p class="mt-2 text-sm text-gray-600">See value, maturity date and performance in your dashboard.</p></x-card>
            <x-card><h3 class="font-black text-brand-700">Certificate issued</h3><p class="mt-2 text-sm text-gray-600">Receive a numbered investment certificate once your funds are confirmed.</p></x-card>
        </div>
        <div class="mt-10 flex flex-wrap gap-3">
            <a href="{{ route('register') }}" class="rounded-md bg-brand-700 px-6 py-3 font-bold text-white hover:bg-brand-800">Become an investor</a>
            <a href="{{ route('login') }}" class="rounded-md border border-brand-700 px-6 py-3 font-bold text-brand-700 hover:bg-brand-50">Sign in</a>
        </div>
        <p class="mt-8 text-sm text-gray-500">Commodity trading involves risk. Returns are a share of actual trading profit and are not guaranteed. Capital is at risk. Read the <a class="underline" href="{{ route('public.risk') }}">risk notice</a>.</p>
    </section>
</x-public-layout>
