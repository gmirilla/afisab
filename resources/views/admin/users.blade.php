@php use Illuminate\Support\Facades\Auth; @endphp
<x-layout title="Users">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Staff accounts</h1>
    <p class="mb-4 text-sm text-gray-600">New staff receive an email link to set their own password. Disabling an account signs the person out immediately. Everyone should switch on two-factor authentication.</p>

    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Add a staff account</h2>
        <form method="POST" action="{{ route('admin.users.store') }}" class="flex flex-wrap items-end gap-3">@csrf
            <x-field name="name" label="Name" required /><x-field name="email" label="Email" type="email" required />
            <label class="block text-sm font-bold text-gray-700">Role<select name="role" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal">@foreach($roles as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach</select></label>
            <x-btn>Create and email invite</x-btn>
        </form>
    </x-card>

    <x-card>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Name</th><th>Email</th><th>Role</th><th>2FA</th><th>Status</th><th></th></tr></thead><tbody>
        @foreach($staff as $u)
            <tr class="border-b align-top last:border-0">
                <td class="py-2">{{ $u->name }}</td><td>{{ $u->email }}</td>
                <td>@if($u->is(auth()->user())) {{ $u->roles->pluck('name')->implode(', ') }} (you)
                    @else
                    <form method="POST" action="{{ route('admin.users.role', $u) }}" class="flex gap-1">@csrf @method('PUT')
                        <select name="role" class="rounded border px-2 py-1 text-sm">@foreach($roles as $r)<option value="{{ $r }}" @selected($u->hasRole($r))>{{ $r }}</option>@endforeach</select><x-btn variant="ghost">Change</x-btn></form>
                    @endif</td>
                <td>{{ $u->two_factor_confirmed_at ? '✔ on' : 'off' }}</td>
                <td>{{ $u->disabled_at ? 'Disabled' : 'Active' }}</td>
                <td>@unless($u->is(auth()->user()))<form method="POST" action="{{ route('admin.users.toggle', $u) }}">@csrf<x-btn variant="{{ $u->disabled_at ? 'primary' : 'danger' }}">{{ $u->disabled_at ? 'Enable' : 'Disable' }}</x-btn></form>@endunless</td>
            </tr>
        @endforeach
        </tbody></table>
    </x-card>
</x-layout>
