<x-layout title="Welcome">
    <section class="py-10 text-center">
        <h1 class="text-4xl font-black text-brand-700">Building Trust, Delivering Excellence</h1>
        <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-600">Participate in Nigeria's agricultural commodity trade through professionally managed, quality-assured trading cycles.</p>
        <div class="mt-8 flex justify-center gap-3">
            <a href="{{ route('register') }}" class="rounded bg-brand-700 px-5 py-3 font-bold text-white">Become an investor</a>
            <a href="{{ route('login') }}" class="rounded border border-brand-700 px-5 py-3 font-bold text-brand-700">Sign in</a>
        </div>
    </section>
    <section class="grid gap-4 md:grid-cols-3">
        <x-card><h3 class="font-black text-brand-700">Minimum ₦500,000</h3><p class="mt-2 text-sm text-gray-600">Choose a 3-month, 6-month or 12-month plan.</p></x-card>
        <x-card><h3 class="font-black text-brand-700">Track your investment</h3><p class="mt-2 text-sm text-gray-600">See value, maturity date and performance in your dashboard.</p></x-card>
        <x-card><h3 class="font-black text-brand-700">Certificate issued</h3><p class="mt-2 text-sm text-gray-600">Receive a numbered investment certificate once your funds are confirmed.</p></x-card>
    </section>
    <p class="mt-8 text-center text-xs text-gray-500">Commodity trading involves risk. Returns are a share of actual trading profit and are not guaranteed.</p>
</x-layout>
