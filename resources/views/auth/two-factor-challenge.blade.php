<x-auth-card title="Two-factor authentication">
    <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4">@csrf
        <x-field name="code" label="Authentication code" inputmode="numeric" autocomplete="one-time-code" autofocus />
        <p class="text-xs text-gray-500">Lost your device? Enter a recovery code instead.</p>
        <x-field name="recovery_code" label="Recovery code" autocomplete="one-time-code" />
        <x-btn class="w-full">Continue</x-btn>
    </form>
</x-auth-card>
