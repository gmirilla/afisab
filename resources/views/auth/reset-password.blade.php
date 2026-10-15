<x-auth-card title="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">@csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-field name="email" label="Email" type="email" :value="$request->email" required />
        <x-field name="password" label="New password" type="password" required />
        <x-field name="password_confirmation" label="Confirm password" type="password" required />
        <x-btn class="w-full">Reset password</x-btn>
    </form>
</x-auth-card>
