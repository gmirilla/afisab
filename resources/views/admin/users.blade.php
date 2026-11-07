@php use Illuminate\Support\Facades\Auth; @endphp
<x-layout title="Users">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Staff accounts</h1>
    <p class="mb-4 text-sm text-gray-600">New staff receive an email link to set their own password. Disabling an account signs the person out immediately. Two-factor authentication is <strong>required by default</strong> for new staff; you can switch it off or on for any person below (not for yourself).</p>
    @if($errors->has('user') || $errors->has('password'))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('user') ?: $errors->first('password') }}</p>@endif

    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Add a staff account</h2>
        <form method="POST" action="{{ route('admin.users.store') }}" class="flex flex-wrap items-end gap-3">@csrf
            <x-field name="name" label="Name" required /><x-field name="email" label="Email" type="email" required />
            <label class="block text-sm font-bold text-gray-700">Role<select name="role" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal">@foreach($roles as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach</select></label>
            <input type="hidden" name="require_two_factor" value="0">
            <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="require_two_factor" value="1" checked> Require two-factor authentication</label>
            <x-btn>Create and email invite</x-btn>
        </form>
    </x-card>

    @php($notRequired = $staff->where('two_factor_required', false)->count())
    @if($notRequired > 0)
        <x-card class="mb-6 border-amber-300 bg-amber-50">
            <h2 class="font-black text-amber-900">{{ $notRequired }} staff {{ $notRequired === 1 ? 'member is' : 'members are' }} not required to use two-factor authentication</h2>
            <p class="mb-3 text-sm text-amber-900">Require it of everyone in one step. Each person is asked to set it up the next time they use the back office (including you, if yours is not set up yet), and cannot turn it off afterwards.</p>
            <form method="POST" action="{{ route('admin.users.two-factor.all') }}" class="flex flex-wrap items-end gap-2" data-confirm="Require two-factor authentication of every staff member?">@csrf
                <input type="password" name="password" placeholder="Your password" required autocomplete="current-password" class="w-48 rounded border px-3 py-2 text-sm">
                <x-btn>Require for all staff</x-btn>
            </form>
        </x-card>
    @else
        <p class="mb-6 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Two-factor authentication is required of every staff member.</p>
    @endif

    <x-card>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Name</th><th>Email</th><th>Role</th><th>Two-factor</th><th>Status</th><th></th></tr></thead><tbody>
        @foreach($staff as $u)
            <tr class="border-b align-top last:border-0">
                <td class="py-2">{{ $u->name }}</td><td>{{ $u->email }}</td>
                <td>@if($u->is(auth()->user())) {{ $u->roles->pluck('name')->implode(', ') }} (you)
                    @else
                    <form method="POST" action="{{ route('admin.users.role', $u) }}" class="flex gap-1">@csrf @method('PUT')
                        <select name="role" class="rounded border px-2 py-1 text-sm">@foreach($roles as $r)<option value="{{ $r }}" @selected($u->hasRole($r))>{{ $r }}</option>@endforeach</select><x-btn variant="ghost">Change</x-btn></form>
                    @endif</td>
                <td>
                    <span class="{{ $u->two_factor_confirmed_at ? 'text-emerald-700' : 'text-gray-500' }}">{{ $u->two_factor_confirmed_at ? '✔ set up' : ($u->two_factor_secret ? 'set-up unfinished' : 'not set up') }}</span><br>
                    <span class="text-xs {{ $u->two_factor_required ? 'font-bold text-brand-700' : 'text-amber-700' }}">{{ $u->two_factor_required ? 'Required' : 'Not required' }}</span>
                    @unless($u->is(auth()->user()))
                        <form method="POST" action="{{ route('admin.users.two-factor', $u) }}" class="mt-1 flex items-center gap-1" data-confirm="{{ $u->two_factor_required ? 'Stop requiring' : 'Require' }} two-factor authentication for {{ $u->name }}?">@csrf @method('PUT')
                            <input type="hidden" name="required" value="{{ $u->two_factor_required ? 0 : 1 }}">
                            <input type="password" name="password" placeholder="Your password" required autocomplete="current-password" class="w-28 rounded border px-2 py-1 text-xs">
                            <x-btn variant="ghost">{{ $u->two_factor_required ? 'Turn off' : 'Turn on' }}</x-btn>
                        </form>
                    @endunless
                </td>
                <td>{{ $u->disabled_at ? 'Disabled' : 'Active' }}</td>
                <td>@unless($u->is(auth()->user()))<form method="POST" action="{{ route('admin.users.toggle', $u) }}">@csrf<x-btn variant="{{ $u->disabled_at ? 'primary' : 'danger' }}">{{ $u->disabled_at ? 'Enable' : 'Disable' }}</x-btn></form>@endunless</td>
            </tr>
        @endforeach
        </tbody></table>
    </x-card>
</x-layout>
