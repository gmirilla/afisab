{{-- Props: $type (quote|supplier|general), $commodities (list of names), $heading, $intro, $button --}}
@php
    $field = 'mt-1 block w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20';
    $label = 'block text-sm font-bold text-gray-700';
    $commodities = $commodities ?? [];
    $selected = old('commodity', request('commodity'));
@endphp
<div id="enquiry" class="scroll-mt-24 rounded-2xl border border-stone-200 bg-white p-6 shadow-[0_8px_30px_-12px_rgba(8,83,42,0.25)] lg:p-8">
    <h2 class="font-display text-2xl font-bold text-brand-700">{{ $heading }}</h2>
    <p class="mt-1 text-sm text-gray-600">{{ $intro }}</p>

    @if(session('enquiry_sent'))
        <div class="mt-5 flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"><x-icon name="check" class="h-5 w-5 shrink-0 text-emerald-600" /><span>{{ session('enquiry_sent') }}</span></div>
    @endif
    @if($errors->any())
        <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc space-y-0.5 pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('public.enquiries.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="started" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) now()->timestamp) }}">
        {{-- Honeypot: invisible to people, tempting to scripts. --}}
        <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">
            <label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <label class="{{ $label }}">{{ $type === 'supplier' ? 'Your name' : 'Full name' }}<input name="name" value="{{ old('name') }}" required maxlength="150" autocomplete="name" class="{{ $field }}"></label>
        <label class="{{ $label }}">{{ $type === 'supplier' ? 'Farm, cooperative or business (optional)' : 'Company' . ($type === 'general' ? ' (optional)' : '') }}<input name="company" value="{{ old('company') }}" maxlength="150" autocomplete="organization" class="{{ $field }}"></label>
        <label class="{{ $label }}">Phone{{ $type === 'general' ? ' (optional)' : '' }}<input name="phone" value="{{ old('phone') }}" @required($type !== 'general') maxlength="32" inputmode="tel" autocomplete="tel" class="{{ $field }}"></label>
        <label class="{{ $label }}">Email{{ $type === 'supplier' ? ' (optional)' : '' }}<input type="email" name="email" value="{{ old('email') }}" @required($type !== 'supplier') maxlength="150" autocomplete="email" class="{{ $field }}"></label>

        @if($type !== 'general')
            <label class="{{ $label }}">{{ $type === 'quote' ? 'Commodity you need' : 'Commodity you can supply' }}
                <select name="commodity" @required($type === 'supplier') class="{{ $field }}">
                    <option value="">{{ $type === 'quote' ? 'Select a commodity' : 'Select a commodity' }}</option>
                    @foreach($commodities as $c)<option value="{{ $c }}" @selected($selected === $c)>{{ $c }}</option>@endforeach
                    <option value="Other" @selected($selected === 'Other')>Other / not listed</option>
                </select></label>
            <label class="{{ $label }}">{{ $type === 'quote' ? 'Quantity needed (e.g. 50 tonnes)' : 'Quantity available (e.g. 20 tonnes)' }}<input name="quantity" value="{{ old('quantity') }}" maxlength="100" class="{{ $field }}"></label>
            <label class="{{ $label }} md:col-span-2">{{ $type === 'quote' ? 'Delivery location' : 'Where is the produce located? (town and state)' }}<input name="location" value="{{ old('location') }}" @required($type === 'supplier') maxlength="150" class="{{ $field }}"></label>
        @endif

        <label class="{{ $label }} md:col-span-2">{{ $type === 'general' ? 'Message' : 'Anything else we should know' . ($type === 'quote' ? ' (grade, packaging, timing)' : ' (grade, packaging, availability)') }}
            <textarea name="message" rows="4" maxlength="2000" @required($type === 'general') class="{{ $field }}">{{ old('message') }}</textarea></label>

        <label class="flex items-start gap-2 text-sm text-gray-700 md:col-span-2">
            <input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-gray-300 text-brand-700 focus:ring-brand-600">
            <span>I agree that AFISAB NIG. LTD may contact me about this enquiry. We use your details only to respond to you, as described in our <a class="underline" href="{{ route('public.privacy') }}">privacy notice</a>.</span>
        </label>

        <div class="md:col-span-2"><x-btn class="px-6 py-3 text-base">{{ $button }}</x-btn></div>
    </form>
</div>
