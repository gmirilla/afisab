<x-layout title="Bank accounts">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Payment bank accounts</h1>
    <p class="mb-4 text-sm text-gray-600">The accounts investors are told to pay into by bank transfer. Changing these redirects real money, so your password is required and every change is recorded in the audit trail (old and new values). Accounts are switched off, never deleted.</p>

    @if($usingEnv)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            No account has been saved here yet, so investors currently see the server setting:
            <strong>{{ $env['name'] ?? '' }} · {{ $env['account_number'] ?? '' }} · {{ $env['account_name'] ?? '' }}</strong>.
            Saving an account below replaces it.
        </div>
    @endif

    <div class="space-y-4">
    @foreach($accounts as $a)
        <x-card class="{{ $a->is_active ? '' : 'opacity-70' }}">
            <form method="POST" action="{{ route('admin.bank-accounts.update', $a) }}" class="grid gap-3 md:grid-cols-6">@csrf @method('PUT')
                <x-field name="bank_name" label="Bank" :value="$a->bank_name" class="md:col-span-2" />
                <x-field name="account_number" label="Account number" :value="$a->account_number" maxlength="10" inputmode="numeric" />
                <x-field name="account_name" label="Account name" :value="$a->account_name" class="md:col-span-2" />
                <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($a->is_active)> Active</label>
                <x-field name="note" label="Note shown to investors (optional)" :value="$a->note" class="md:col-span-3" />
                <x-field name="password" label="Your password" type="password" required autocomplete="current-password" class="md:col-span-2" />
                <div class="flex items-end"><x-btn>Save</x-btn></div>
            </form>
        </x-card>
    @endforeach
        <x-card>
            <h2 class="mb-3 font-black text-brand-700">Add account</h2>
            <form method="POST" action="{{ route('admin.bank-accounts.store') }}" class="grid gap-3 md:grid-cols-6">@csrf
                <x-field name="bank_name" label="Bank" class="md:col-span-2" />
                <x-field name="account_number" label="Account number" maxlength="10" inputmode="numeric" />
                <x-field name="account_name" label="Account name" value="AFISAB NIG. LTD" class="md:col-span-3" />
                <x-field name="note" label="Note shown to investors (optional)" class="md:col-span-3" />
                <x-field name="password" label="Your password" type="password" required autocomplete="current-password" class="md:col-span-2" />
                <div class="flex items-end"><x-btn>Add account</x-btn></div>
            </form>
        </x-card>
    </div>
</x-layout>
