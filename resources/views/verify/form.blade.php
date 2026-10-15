<x-auth-card title="Verify a certificate">
    <p class="mb-4 text-sm text-gray-600">Enter the verification code printed beneath the QR code on the certificate, or scan the QR code.</p>
    <form method="POST" action="{{ route('verify.lookup') }}" class="space-y-4">@csrf
        <x-field name="code" label="Verification code" required autofocus />
        <x-btn class="w-full">Verify</x-btn>
    </form>
</x-auth-card>
