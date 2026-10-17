<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('afisab.company_name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ config('afisab.company_name') }} · Building Trust, Delivering Excellence
Returns are a share of actual trading profit and are not guaranteed. This is an automated message; please do not reply to it.
© {{ date('Y') }} {{ config('afisab.company_name') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
