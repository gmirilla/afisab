<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    /** Send each user to the right home area for their role. */
    public function __invoke(): RedirectResponse
    {
        return auth()->user()->isStaff()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('investor.dashboard');
    }
}
