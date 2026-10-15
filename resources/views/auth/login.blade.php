<x-auth-card title="Sign in">
    <form method="POST" action="{{ route('login') }}" class="space-y-4">@csrf
        <x-field name="email" label="Email" type="email" required autofocus />
        <x-field name="password" label="Password" type="password" required />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> Remember me</label>
        <x-btn class="w-full">Sign in</x-btn>
    </form>
    <p class="mt-4 text-sm"><a class="text-brand-700 underline" href="{{ route('password.request') }}">Forgot password?</a> · <a class="text-brand-700 underline" href="{{ route('register') }}">Create account</a></p>
</x-auth-card>
