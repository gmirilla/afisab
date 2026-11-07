<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/** Staff accounts: create, change role, disable. Investors are not managed here. */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', [
            'staff' => User::whereHas('roles', fn ($q) => $q->where('name', '!=', 'investor'))->with('roles')->orderBy('name')->get(),
            'roles' => Role::where('name', '!=', 'investor')->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::exists('roles', 'name')->where(fn ($q) => $q->where('name', '!=', 'investor'))],
            'require_two_factor' => ['sometimes', 'boolean'],
        ]);

        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make(Str::random(40))]);
        // On unless the super-admin unticks it here: the default for every new staff account.
        $user->forceFill(['email_verified_at' => now(), 'two_factor_required' => $request->boolean('require_two_factor', true)])->save();
        $user->assignRole($data['role']);
        activity()->performedOn($user)->causedBy($request->user())->withProperties(['role' => $data['role'], 'two_factor_required' => $user->two_factor_required])->log('Staff account created');

        // The new person sets their own password through the normal reset link.
        Password::sendResetLink(['email' => $user->email]);

        return back()->with('status', "Account created for {$user->name}. A link to set their password was emailed to {$user->email}.");
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::exists('roles', 'name')->where(fn ($q) => $q->where('name', '!=', 'investor'))]]);

        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot change your own role.']);
        }
        if ($user->hasRole('super-admin') && $data['role'] !== 'super-admin' && $this->lastSuperAdmin($user)) {
            return back()->withErrors(['user' => 'At least one active super-admin must remain.']);
        }

        $user->syncRoles([$data['role']]);
        activity()->performedOn($user)->causedBy($request->user())->withProperties(['role' => $data['role']])->log('Staff role changed');

        return back()->with('status', 'Role updated.');
    }

    /** The super-admin switches the two-factor requirement on or off for one staff member. Needs their password; audited. */
    public function updateTwoFactor(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['required' => ['required', 'boolean'], 'password' => ['required', 'current_password']], ['password.current_password' => 'Your password is incorrect.']);

        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot change the two-factor requirement on your own account.']);
        }
        abort_unless($user->isStaff(), 404);

        $required = (bool) $data['required'];
        $user->forceFill(['two_factor_required' => $required])->save();
        activity()->performedOn($user)->causedBy($request->user())->withProperties(['required' => $required])->log($required ? 'Two-factor requirement turned on' : 'Two-factor requirement turned off');

        return back()->with('status', $required
            ? "Two-factor authentication is now required for {$user->name}".($user->two_factor_confirmed_at ? '.' : '. They will be asked to set it up the next time they use the back office.')
            : "Two-factor authentication is no longer required for {$user->name}.");
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot disable your own account.']);
        }

        if ($user->disabled_at === null) {
            if ($user->hasRole('super-admin') && $this->lastSuperAdmin($user)) {
                return back()->withErrors(['user' => 'At least one active super-admin must remain.']);
            }
            $user->forceFill(['disabled_at' => now()])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete(); // end any open sessions now
            $message = 'Account disabled and signed out.';
        } else {
            $user->forceFill(['disabled_at' => null])->save();
            $message = 'Account re-enabled.';
        }
        activity()->performedOn($user)->causedBy($request->user())->log($message);

        return back()->with('status', $message);
    }

    private function lastSuperAdmin(User $except): bool
    {
        return ! User::role('super-admin')->whereNull('disabled_at')->where('id', '!=', $except->id)->exists();
    }
}
