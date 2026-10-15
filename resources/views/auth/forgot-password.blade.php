<x-auth-card title="Reset your password">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">@csrf
        <x-field name="email" label="Email" type="email" required autofocus />
        <x-btn class="w-full">Email reset link</x-btn>
    </form>
</x-auth-card>
