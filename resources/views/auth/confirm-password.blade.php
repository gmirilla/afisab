<x-auth-card title="Confirm your password">
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">@csrf
        <x-field name="password" label="Password" type="password" required autofocus />
        <x-btn class="w-full">Confirm</x-btn>
    </form>
</x-auth-card>
