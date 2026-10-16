<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PensionAdministrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Pension fund administrators (PFAs). Staff each belong to one; remittances are paid to each PFA's account. */
class PensionAdministratorController extends Controller
{
    public function index(): View
    {
        return view('admin.hr.pension-administrators', [
            'administrators' => PensionAdministrator::withCount('employees')->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function save(Request $request, ?PensionAdministrator $administrator = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('pension_administrators', 'name')->ignore($administrator?->id)],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'digits:10'],
            'account_name' => ['nullable', 'string', 'max:150'],
        ]);
        $data['is_active'] = $request->boolean('is_active', ! $administrator);

        $saved = $administrator ? tap($administrator)->update($data) : PensionAdministrator::create($data);
        activity()->performedOn($saved)->causedBy($request->user())->log($administrator ? 'Pension administrator changed' : 'Pension administrator added');

        return back()->with('status', 'Pension administrator saved.');
    }
}
