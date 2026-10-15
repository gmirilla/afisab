<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/** Two-factor authentication set-up for the signed-in user (staff and investors). */
class SecurityController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $state = $user->two_factor_confirmed_at ? 'enabled' : ($user->two_factor_secret ? 'pending' : 'disabled');

        return view('account.security', [
            'state' => $state,
            'qr' => $state === 'pending' ? $user->twoFactorQrCodeSvg() : null,
            'secret' => $state === 'pending' ? decrypt($user->two_factor_secret) : null,
            'recoveryCodes' => $state === 'enabled' ? $user->recoveryCodes() : [],
            'required' => (bool) config('afisab.require_2fa_for_staff') && $user->isStaff(),
        ]);
    }

    public function enable(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $this->assertPassword($request);
        $enable($request->user(), force: $request->user()->two_factor_confirmed_at === null);
        activity()->causedBy($request->user())->log('Two-factor authentication set-up started');

        return redirect()->route('account.security');
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);

        try {
            $confirm($request->user(), $request->input('code'));
        } catch (ValidationException) {
            return back()->withErrors(['code' => 'That code is not valid. Check the time on your phone and try the next code.']);
        }
        activity()->causedBy($request->user())->log('Two-factor authentication enabled');

        return redirect()->route('account.security')->with('status', 'Two-factor authentication is on. Save your recovery codes somewhere safe.');
    }

    public function recoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $this->assertPassword($request);
        abort_unless($request->user()->two_factor_confirmed_at, 403);
        $generate($request->user());
        activity()->causedBy($request->user())->log('Two-factor recovery codes regenerated');

        return back()->with('status', 'New recovery codes generated. The old ones no longer work.');
    }

    public function disable(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $this->assertPassword($request);

        if ((bool) config('afisab.require_2fa_for_staff') && $request->user()->isStaff()) {
            return back()->withErrors(['password' => 'Two-factor authentication is required for staff accounts and cannot be turned off.']);
        }

        $disable($request->user());
        activity()->causedBy($request->user())->log('Two-factor authentication disabled');

        return back()->with('status', 'Two-factor authentication has been turned off.');
    }

    private function assertPassword(Request $request): void
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'That password is not correct.']);
        }
    }
}
