<x-auth-card title="Become an investor">
    <form method="POST" action="{{ route('register') }}" class="space-y-4">@csrf
        <x-field name="name" label="Full name" required autofocus />
        <x-field name="email" label="Email" type="email" required />
        <x-field name="phone" label="Phone number" type="tel" required />
        <x-field name="password" label="Password" type="password" required autocomplete="new-password" />
        <x-field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
        <x-password-rules />
        <x-btn class="w-full">Create account</x-btn>
    </form>
    <p class="mt-4 text-sm">Already registered? <a class="text-brand-700 underline" href="{{ route('login') }}">Sign in</a></p>
</x-auth-card>
