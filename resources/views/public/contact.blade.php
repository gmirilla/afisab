<x-public-layout title="Contact" description="Contact AFISAB NIG. LTD by phone, email or WhatsApp, or send a message through our website.">
    @php($phone = config('afisab.contact.phone'))
    <x-page-banner eyebrow="Contact" title="Get in touch" lead="Buyers, suppliers and anyone with a question: we would like to hear from you." />

    <section class="mx-auto grid max-w-7xl gap-12 px-4 py-14 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-2">
            <a href="tel:{{ $phone }}" class="flex items-center gap-4 rounded-xl border border-stone-200 p-5 transition hover:border-brand-600 hover:bg-brand-50">
                <span class="grid h-12 w-12 place-items-center rounded-lg bg-brand-700 text-white"><x-icon name="phone" /></span>
                <span><span class="block text-xs font-bold uppercase tracking-wider text-gray-500">Phone</span><span class="font-bold text-brand-700">{{ preg_replace('/^\+234(\d{3})(\d{3})(\d{4})$/', '+234 $1 $2 $3', $phone) }}</span></span>
            </a>
            <a href="mailto:{{ config('afisab.contact.email') }}" class="flex items-center gap-4 rounded-xl border border-stone-200 p-5 transition hover:border-brand-600 hover:bg-brand-50">
                <span class="grid h-12 w-12 place-items-center rounded-lg bg-brand-700 text-white"><x-icon name="mail" /></span>
                <span><span class="block text-xs font-bold uppercase tracking-wider text-gray-500">Email</span><span class="font-bold text-brand-700">{{ config('afisab.contact.email') }}</span></span>
            </a>
            <a href="https://wa.me/{{ ltrim($phone, '+') }}" rel="noopener" class="flex items-center gap-4 rounded-xl border border-stone-200 p-5 transition hover:border-brand-600 hover:bg-brand-50">
                <span class="grid h-12 w-12 place-items-center rounded-lg bg-brand-700 text-white"><x-icon name="chat" /></span>
                <span><span class="block text-xs font-bold uppercase tracking-wider text-gray-500">WhatsApp</span><span class="font-bold text-brand-700">Message us</span></span>
            </a>
            <p class="text-sm text-gray-600">Looking to buy? <a class="font-bold text-brand-700 underline" href="{{ route('public.buyers') }}#enquiry">Request a quote</a>. Have produce to sell? <a class="font-bold text-brand-700 underline" href="{{ route('public.suppliers') }}#enquiry">Register your interest</a>.</p>
        </div>
        <div class="lg:col-span-3">
            @include('public._enquiry-form', ['type' => 'general', 'heading' => 'Send us a message', 'intro' => 'We will reply using the details you give.', 'button' => 'Send message'])
        </div>
    </section>
</x-public-layout>
