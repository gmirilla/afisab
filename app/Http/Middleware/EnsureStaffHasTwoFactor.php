<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff move money and see investors' personal data, so when enforcement is on they must have confirmed
 * two-factor authentication before they can use anything except the security page and sign-out.
 */
class EnsureStaffHasTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            config('afisab.require_2fa_for_staff')
            && $user
            && $user->two_factor_confirmed_at === null
            && ! $request->routeIs('account.security*', 'logout')
            && $user->isStaff()
        ) {
            return redirect()->route('account.security')
                ->with('status', 'Staff accounts must turn on two-factor authentication before continuing.');
        }

        return $next($request);
    }
}
