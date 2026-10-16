@php use App\Support\Money; use App\Enums\PaymentStatus; @endphp
<x-layout title="Paystack">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Paystack online payments</h1>
    <p class="mb-4 text-sm text-gray-600">Card and bank checkout for investors. These keys move real money, so only the super-admin can see this page, keys are stored encrypted and shown masked, and every change needs your password and is recorded in the audit trail (the keys themselves are never logged).</p>

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <x-card>
            <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Status</p>
            @if($settings->enabled())
                <p class="mt-1 text-xl font-black text-emerald-700">Online payments ON</p>
            @elseif($settings->configured())
                <p class="mt-1 text-xl font-black text-amber-700">Switched OFF</p>
                <p class="text-xs text-gray-500">Investors see bank transfer only. Payments already started still settle.</p>
            @else
                <p class="mt-1 text-xl font-black text-red-700">Not configured</p>
            @endif
        </x-card>
        <x-card>
            <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Mode</p>
            @if($settings->mode() === 'live')
                <p class="mt-1 text-xl font-black text-emerald-700">LIVE</p>
                <p class="text-xs text-gray-500">Real money.</p>
            @elseif($settings->mode() === 'test')
                <p class="mt-1 text-xl font-black text-amber-700">TEST</p>
                <p class="text-xs {{ $production ? 'font-bold text-red-700' : 'text-gray-500' }}">{{ $production ? 'This is production: switch to live keys before launch.' : 'No real money moves.' }}</p>
            @else
                <p class="mt-1 text-xl font-black text-gray-400">Unknown</p>
            @endif
            <p class="mt-1 text-xs text-gray-500">Keys come from: {{ $settings->source() }}</p>
        </x-card>
        <x-card>
            <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Last connection test</p>
            @if($row?->last_tested_at)
                <p class="mt-1 text-xl font-black {{ $row->last_test_ok ? 'text-emerald-700' : 'text-red-700' }}">{{ $row->last_test_ok ? 'Passed' : 'Failed' }}</p>
                <p class="text-xs text-gray-500">{{ $row->last_tested_at->format('d M Y H:i') }} · {{ $row->last_test_message }}</p>
            @else
                <p class="mt-1 text-xl font-black text-gray-400">Not tested</p>
            @endif
            @if($settings->configured())
                <form method="POST" action="{{ route('admin.paystack.test') }}" class="mt-3">@csrf<x-btn variant="ghost">Test connection</x-btn></form>
            @endif
        </x-card>
    </div>

    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Keys and switch</h2>
        <form method="POST" action="{{ route('admin.paystack.save') }}" class="grid gap-3 md:grid-cols-2">@csrf
            <x-field name="secret_key" label="Secret key{{ $secretMasked ? ' (current: '.$secretMasked.')' : '' }}" placeholder="{{ $secretMasked ? 'Leave blank to keep the current key' : 'sk_live_…' }}" autocomplete="off" />
            <x-field name="public_key" label="Public key{{ $publicMasked ? ' (current: '.$publicMasked.')' : '' }}" placeholder="{{ $publicMasked ? 'Leave blank to keep the current key' : 'pk_live_…' }}" autocomplete="off" />
            <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $row ? $row->enabled : true))> Let investors pay online</label>
            <x-field name="password" label="Your password" type="password" required autocomplete="current-password" />
            <div class="flex items-end"><x-btn>Save</x-btn></div>
        </form>
    </x-card>

    <x-card class="mb-6">
        <h2 class="mb-2 font-black text-brand-700">Set these in your Paystack dashboard</h2>
        <p class="mb-2 text-sm text-gray-600">Settings → API Keys &amp; Webhooks. Without the webhook, a payment is only confirmed when the investor returns to the site.</p>
        <dl class="space-y-2 text-sm">
            <div><dt class="font-bold">Webhook URL</dt><dd class="font-mono text-xs">{{ $webhookUrl }}</dd></div>
            <div><dt class="font-bold">Callback URL</dt><dd class="font-mono text-xs">{{ $callbackUrl }}</dd></div>
        </dl>
    </x-card>

    <x-card>
        <h2 class="mb-3 font-black text-brand-700">Recent online payments</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Date</th><th>Reference</th><th>Investor</th><th class="text-right">Amount</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($payments as $p)
            <tr class="border-b last:border-0">
                <td class="py-2">{{ $p->created_at->format('d M Y H:i') }}</td>
                <td class="font-mono text-xs">{{ $p->reference }}@if($p->gateway_reference)<br><span class="text-gray-500">Paystack #{{ $p->gateway_reference }}</span>@endif</td>
                <td>{{ $p->investment->user->name }}<br><span class="font-mono text-xs text-gray-500">{{ $p->investment->reference }}</span></td>
                <td class="text-right font-mono">{{ Money::format($p->amount) }}</td>
                <td>{{ $p->status->label() }}</td>
                <td class="text-right">@if($p->status === PaymentStatus::Pending)
                    <form method="POST" action="{{ route('admin.paystack.reverify', $p) }}">@csrf<x-btn variant="ghost">Re-check with Paystack</x-btn></form>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-6 text-center text-gray-500">No online payments yet.</td></tr>
        @endforelse
        </tbody></table>
    </x-card>
</x-layout>
