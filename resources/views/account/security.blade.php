<x-layout title="Account security">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-1 text-2xl font-black text-brand-700">Account security</h1>
        <p class="mb-6 text-sm text-gray-600">Two-factor authentication asks for a 6-digit code from an authenticator app (Google Authenticator, Microsoft Authenticator, Authy) every time you sign in, so a stolen password alone is not enough.
            @if($required) <strong>It is required for staff accounts.</strong> @endif</p>

        @if($state === 'disabled')
            <x-card>
                <p class="mb-3">Two-factor authentication is <strong>off</strong>.</p>
                <form method="POST" action="{{ route('account.security.enable') }}" class="space-y-3">@csrf
                    <x-field name="password" label="Confirm your password to begin" type="password" required />
                    <x-btn>Set up two-factor authentication</x-btn>
                </form>
            </x-card>
        @elseif($state === 'pending')
            <x-card>
                <p class="mb-3"><strong>Step 1.</strong> Scan this code with your authenticator app.</p>
                <div class="mb-3 inline-block rounded border bg-white p-2">{!! $qr !!}</div>
                <p class="mb-4 text-sm text-gray-600">Cannot scan? Enter this key by hand: <span class="font-mono font-bold">{{ $secret }}</span></p>
                <p class="mb-3"><strong>Step 2.</strong> Type the 6-digit code the app shows to finish.</p>
                <form method="POST" action="{{ route('account.security.confirm') }}" class="flex flex-wrap items-end gap-3">@csrf
                    <x-field name="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" required />
                    <x-btn>Confirm and turn on</x-btn>
                </form>
            </x-card>
        @else
            <x-card class="mb-6">
                <p class="mb-3">Two-factor authentication is <strong class="text-green-700">on</strong>.</p>
                <h2 class="mb-2 font-black text-brand-700">Recovery codes</h2>
                <p class="mb-2 text-sm text-gray-600">Each code works once if you lose your phone. Keep them somewhere safe and private.</p>
                <div class="mb-4 grid grid-cols-2 gap-1 rounded bg-gray-50 p-3 font-mono text-sm">@foreach($recoveryCodes as $code)<span>{{ $code }}</span>@endforeach</div>
                <form method="POST" action="{{ route('account.security.recovery') }}" class="flex flex-wrap items-end gap-3">@csrf
                    <x-field name="password" label="Password" type="password" required /><x-btn variant="ghost">Generate new recovery codes</x-btn>
                </form>
            </x-card>
            @unless($required)
                <x-card>
                    <form method="POST" action="{{ route('account.security.disable') }}" class="flex flex-wrap items-end gap-3" data-confirm="Turn off two-factor authentication?">@csrf @method('DELETE')
                        <x-field name="password" label="Password" type="password" required /><x-btn variant="danger">Turn off</x-btn>
                    </form>
                </x-card>
            @endunless
        @endif
    </div>
</x-layout>
