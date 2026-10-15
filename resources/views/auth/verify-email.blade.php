<x-auth-card title="Verify your email">
    <p class="mb-4 text-sm text-gray-600">We sent a verification link to your email address. Please click it to continue.</p>
    <form method="POST" action="{{ route('verification.send') }}">@csrf
        <x-btn variant="ghost">Resend verification email</x-btn>
    </form>
</x-auth-card>
