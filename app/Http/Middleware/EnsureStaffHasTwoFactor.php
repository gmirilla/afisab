<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff move money and see investors' personal data, so a staff member the super-admin has marked as "two-factor required"
 * (the default for every new staff account) must have confirmed two-factor authentication before they can use anything
 * except the security page and sign-out.
 */
class EnsureStaffHasTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->two_factor_confirmed_at === null
            && ! $request->routeIs('account.security*', 'logout')
            && $user->requiresTwoFactor()
        ) {
            return redirect()->route('account.security')
                ->with('status', 'Staff accounts must turn on two-factor authentication before continuing.');
        }

        return $next($request);
    }
}
