<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Public certificate verification. Reveals only a masked holder name and basic policy facts. */
class VerifyController extends Controller
{
    public function form(): View
    {
        return view('verify.form');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        return redirect()->route('verify.show', Str::lower(trim($data['code'])));
    }

    public function show(string $token): View
    {
        $investment = Investment::with(['user', 'plan'])->where('certificate_token', $token)->first();

        return view('verify.show', [
            'investment' => $investment && $investment->hasCertificate() ? $investment : null,
            'maskedName' => $investment ? $this->mask($investment->user->name) : null,
        ]);
    }

    /** "Adaeze Okonkwo" -> "A*** O***" (fixed width so name length isn't leaked) */
    private function mask(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))
            ->map(fn ($part) => Str::substr($part, 0, 1).'***')
            ->implode(' ');
    }
}
