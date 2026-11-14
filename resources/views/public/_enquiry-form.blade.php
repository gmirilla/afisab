{{-- Props: $type (quote|supplier|general), $commodities (list of commodity names) --}}
@php
    $isQuote = $type === 'quote';
    $isSupplier = $type === 'supplier';
    $selected = (string) old('items.0.commodity', request('commodity', ''));
    $units = \App\Models\Enquiry::UNITS;
@endphp
<form class="enquiry-panel enquiry-form" method="POST" action="{{ route('public.enquiries.store') }}" novalidate>
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <input type="hidden" name="started" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) now()->timestamp) }}">
    <p class="required-note">Fields marked with * are required.</p>

    @if(session('enquiry_sent'))
        <p class="enquiry-status" role="status" tabindex="-1" id="enquiry-sent">{{ session('enquiry_sent') }}</p>
    @endif
    @if($errors->any())
        <div class="enquiry-errors" role="alert" tabindex="-1" id="enquiry-errors"><strong>Please check the following</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif

    <fieldset>
        <legend><span aria-hidden="true">01</span> {{ $isQuote ? 'Buyer information' : ($isSupplier ? 'Supplier information' : 'Your details') }}</legend>
        <div class="enquiry-fields">
            <x-site-field name="full_name" label="Full name" :required="true" autocomplete="name" maxlength="150" errorKey="name" />
            <x-site-field name="company" :label="$isQuote ? 'Company name' : ($isSupplier ? 'Farm, cooperative or business' : 'Company (optional)')" :required="$isQuote" autocomplete="organization" maxlength="150" />
            <x-site-field name="email" :label="$isQuote ? 'Business email' : ($isSupplier ? 'Email (optional)' : 'Email')" type="email" :required="! $isSupplier" autocomplete="email" maxlength="150" />
            <x-site-field name="phone" :label="$type === 'general' ? 'Phone number (optional)' : 'Phone number'" type="tel" :required="$type !== 'general'" autocomplete="tel" maxlength="32" placeholder="+234 …" />
            @if($isQuote)
                <x-site-field name="country" label="Country" :required="true" autocomplete="country-name" maxlength="80" />
                <x-site-field name="buyer_type" label="Buyer type" type="select" :required="true" :options="\App\Models\Enquiry::BUYER_TYPES" placeholderOption="Select buyer type" />
            @endif
        </div>
    </fieldset>

    @if($isQuote || $isSupplier)
    <fieldset>
        <legend><span aria-hidden="true">02</span> {{ $isQuote ? 'Commodities and delivery' : 'What you can supply' }}</legend>
        <p class="required-note enquiry-hint">{{ $isQuote ? 'List each commodity you need with the quantity.' : 'List each commodity you can supply with the estimated quantity.' }} You can add up to {{ \App\Models\Enquiry::MAX_ITEMS }}.</p>
        <div class="enquiry-items">
            @for($n = 0; $n < \App\Models\Enquiry::MAX_ITEMS; $n++)
                @php
                    $name = (string) old("items.$n.commodity", $n === 0 ? $selected : '');
                    $isOther = $name !== '' && ! in_array($name, $commodities, true);
                    $extra = $n >= 3 && $name === '' && ! old("items.$n.quantity");
                @endphp
                <div class="enquiry-item" @if($extra) data-extra-row hidden @endif>
                    <div class="trade-field">
                        <label for="items_{{ $n }}_commodity">Commodity {{ $n + 1 }} @if($n === 0)<span aria-hidden="true">*</span>@else<span class="optional">(optional)</span>@endif</label>
                        <select id="items_{{ $n }}_commodity" name="items[{{ $n }}][commodity]" @required($n === 0) data-commodity-select>
                            <option value="">Select commodity</option>
                            @foreach($commodities as $c)<option value="{{ $c }}" @selected($name === $c)>{{ $c }}</option>@endforeach
                            <option value="Other" @selected($isOther)>Other (type its name)</option>
                        </select>
                        <input type="text" name="items[{{ $n }}][other]" value="{{ $isOther ? $name : old("items.$n.other") }}" maxlength="100" placeholder="If you chose Other, type the commodity" aria-label="Name of the other commodity {{ $n + 1 }}" data-other-input>
                    </div>
                    <div class="trade-field">
                        <label for="items_{{ $n }}_quantity">{{ $isQuote ? 'Required quantity' : 'Estimated quantity' }} @if($n === 0)<span aria-hidden="true">*</span>@endif</label>
                        <input type="text" id="items_{{ $n }}_quantity" name="items[{{ $n }}][quantity]" value="{{ old("items.$n.quantity") }}" inputmode="decimal" maxlength="40" placeholder="e.g. 50" @required($n === 0)>
                    </div>
                    <div class="trade-field">
                        <label for="items_{{ $n }}_unit">Unit @if($n === 0)<span aria-hidden="true">*</span>@endif</label>
                        <select id="items_{{ $n }}_unit" name="items[{{ $n }}][unit]" @required($n === 0)>
                            <option value="">Select unit</option>
                            @foreach($units as $k => $label)<option value="{{ $k }}" @selected(old("items.$n.unit") === $k)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>
            @endfor
        </div>
        <button type="button" hidden data-add-row class="text-link enquiry-add">+ Add another commodity</button>

        <div class="enquiry-fields enquiry-fields--after-items">
            @if($isQuote)
                <x-site-field name="destination" label="Delivery destination" :required="true" maxlength="120" placeholder="City, country or port" />
                <x-site-field name="delivery_date" label="Expected delivery date" type="date" />
                <x-site-field name="preferred_grade" label="Preferred grade" maxlength="100" />
                <x-site-field name="packaging" label="Packaging preference" maxlength="120" />
                <x-site-field name="quality_requirements" label="Quality requirements" type="textarea" :wide="true" maxlength="550" hint="Include any target specifications, tolerances or testing needs." />
                <x-site-field name="notes" label="Additional requirements" type="textarea" :wide="true" maxlength="500" errorKey="message" />
            @else
                <x-site-field name="location" label="State or location" :required="true" maxlength="150" placeholder="State and local government area" />
                <x-site-field name="readiness" label="Harvest or readiness status" type="select" :required="true" :options="\App\Models\Enquiry::READINESS" placeholderOption="Select harvest or readiness status" />
                <x-site-field name="available_from" label="Available from" type="date" />
                <x-site-field name="notes" label="Additional information" type="textarea" :wide="true" maxlength="550" errorKey="message" hint="Grade, packaging, availability or anything else we should know." />
            @endif
        </div>
    </fieldset>
    @else
    <fieldset>
        <legend><span aria-hidden="true">02</span> Your message</legend>
        <div class="enquiry-fields">
            <x-site-field name="message" label="Message" type="textarea" :required="true" :wide="true" maxlength="2000" rows="6" />
        </div>
    </fieldset>
    @endif

    <div class="enquiry-trap" aria-hidden="true"><label for="enquiry-website">Leave blank</label><input id="enquiry-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>

    <label class="enquiry-consent">
        <input type="checkbox" name="consent" value="1" required @checked(old('consent'))>
        <span>I agree that AFISAB NIG. LTD may contact me about this enquiry. We use your details only to respond to you, as described in our <a class="text-link" href="{{ route('public.privacy') }}">privacy notice</a>.</span>
    </label>

    <div class="button-row">
        <button type="submit" class="button button--forest">{{ $isQuote ? 'Send quote request' : ($isSupplier ? 'Send supplier enquiry' : 'Send message') }}
            <svg class="icon icon--arrow" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></button>
    </div>
</form>
