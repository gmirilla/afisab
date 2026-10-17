@php($phone = config('afisab.contact.phone'))
<section class="bg-stone-50">
    <div class="mx-auto max-w-7xl px-4 py-14">
        <div class="grid items-center gap-8 rounded-2xl bg-gradient-to-br from-brand-800 to-brand-700 p-8 text-white shadow-xl lg:grid-cols-3 lg:p-12">
            <div class="lg:col-span-2">
                <h2 class="font-display text-2xl font-bold lg:text-3xl">Talk to AFISAB</h2>
                <p class="mt-2 max-w-xl text-white/85">Whether you buy commodities in volume or have produce to sell, send us the details and we will respond.</p>
            </div>
            <div class="space-y-2 text-sm">
                <a class="flex items-center gap-3 hover:underline" href="tel:{{ $phone }}"><x-icon name="phone" class="h-5 w-5 text-gold-500" />{{ preg_replace('/^\+234(\d{3})(\d{3})(\d{4})$/', '+234 $1 $2 $3', $phone) }}</a>
                <a class="flex items-center gap-3 hover:underline" href="mailto:{{ config('afisab.contact.email') }}"><x-icon name="mail" class="h-5 w-5 text-gold-500" />{{ config('afisab.contact.email') }}</a>
                <a class="flex items-center gap-3 hover:underline" href="https://wa.me/{{ ltrim($phone, '+') }}" rel="noopener"><x-icon name="chat" class="h-5 w-5 text-gold-500" />Message us on WhatsApp</a>
            </div>
        </div>
    </div>
</section>
