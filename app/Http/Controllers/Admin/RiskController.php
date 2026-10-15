<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Concerns\AttemptsActions;
use App\Http\Controllers\Controller;
use App\Models\TakafulPolicy;
use App\Services\Risk\RiskService;
use App\Services\Risk\TakafulService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class RiskController extends Controller
{
    use AttemptsActions;

    public function index(RiskService $risk): View
    {
        return view('admin.risk', [
            'dash' => $risk->dashboard(),
            'definitions' => RiskService::LIMITS,
            'policies' => TakafulPolicy::orderByDesc('end_date')->get(),
        ]);
    }

    public function saveLimits(Request $request, RiskService $risk): RedirectResponse
    {
        $request->validate(['limits' => ['required', 'array']]);

        try {
            foreach (RiskService::LIMITS as $key => $def) {
                $row = $request->input("limits.$key", []);
                $raw = trim((string) ($row['value'] ?? ''));
                $value = null;
                if ($raw !== '') {
                    $value = $def['unit'] === 'percent' ? (int) round(((float) $raw) * 100) : Money::fromNaira($raw);
                }
                $risk->save($key, $value, (bool) ($row['active'] ?? false), $request->user());
            }
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['limits' => 'Enter valid amounts (naira) and percentages.']);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->withErrors(['limits' => $e->getMessage()]);
        }

        return back()->with('status', 'Risk limits saved.');
    }

    public function storeTakaful(Request $request, TakafulService $takaful): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:150'],
            'policy_number' => ['required', 'string', 'max:64', 'unique:takaful_policies,policy_number'],
            'description' => ['required', 'string', 'max:255'],
            'premium' => ['required', 'string', 'max:20'],
            'cover_amount' => ['required', 'string', 'max:20'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        return $this->attempt(fn () => $takaful->create([
            'provider' => $data['provider'], 'policy_number' => $data['policy_number'], 'description' => $data['description'],
            'premium' => Money::fromNaira($data['premium']), 'cover_amount' => Money::fromNaira($data['cover_amount']),
            'start_date' => $data['start_date'], 'end_date' => $data['end_date'],
        ], $request->user()), 'Takaful policy recorded.');
    }

    public function payTakaful(Request $request, TakafulPolicy $policy, TakafulService $takaful): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:64']]);

        return $this->attempt(fn () => $takaful->payPremium($policy, $data['reference'], $request->user()), 'Premium paid from the Risk Fund.');
    }
}
