@php use App\Support\Money; @endphp
<x-layout title="Certificate verification">
    <div class="mx-auto max-w-xl">
        @if($investment)
            <x-card>
                <p class="mb-2 inline-block rounded bg-green-100 px-3 py-1 text-sm font-bold text-green-800">✔ Valid AFISAB certificate</p>
                <dl class="mt-3 grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-gray-500">Certificate no.</dt><dd class="font-mono font-bold">{{ $investment->certificate_number }}</dd>
                    <dt class="text-gray-500">Holder</dt><dd>{{ $maskedName }}</dd>
                    <dt class="text-gray-500">Plan</dt><dd>{{ $investment->plan->name }}</dd>
                    <dt class="text-gray-500">Amount</dt><dd>{{ Money::format($investment->amount) }}</dd>
                    <dt class="text-gray-500">Start</dt><dd>{{ $investment->start_date->format('d M Y') }}</dd>
                    <dt class="text-gray-500">Maturity</dt><dd>{{ $investment->maturity_date->format('d M Y') }}</dd>
                    <dt class="text-gray-500">Status</dt><dd>{{ $investment->status->label() }}</dd>
                </dl>
                <p class="mt-4 text-xs text-gray-500">Holder details are partly hidden for privacy. Compare the name on the certificate with the first letters shown.</p>
            </x-card>
        @else
            <x-card>
                <p class="font-bold text-red-700">No certificate found for this code.</p>
                <p class="mt-2 text-sm text-gray-600">Check the code and try again. If you believe the certificate is genuine, contact AFISAB.</p>
                <a class="mt-3 inline-block text-brand-700 underline" href="{{ route('verify.form') }}">Try again</a>
            </x-card>
        @endif
    </div>
</x-layout>
