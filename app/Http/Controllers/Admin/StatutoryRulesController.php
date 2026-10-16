<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\StatutoryRuleSet;
use App\Services\Hr\StatutoryCalculator;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * PAYE, pension and NHF rates. Super-admin only (statutory.manage); HR and Directors can read them.
 * A set is edited as a draft, then activated with the super-admin's password and is locked from then on:
 * a change in law is a NEW set with a later effective date, so past payrolls always show the rules they used.
 */
class StatutoryRulesController extends Controller
{
    public function index(): View
    {
        return view('admin.statutory', [
            'sets' => StatutoryRuleSet::orderByDesc('effective_from')->orderByDesc('id')->get(),
            'inForce' => StatutoryRuleSet::forPeriod(now()->format('Y-m')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new StatutoryRuleSet);
    }

    public function update(Request $request, StatutoryRuleSet $set): RedirectResponse
    {
        if ($set->status !== StatutoryRuleSet::DRAFT) {
            return back()->withErrors(['statutory' => 'An active rule set is locked. Copy it as a new draft to change it.']);
        }

        return $this->save($request, $set);
    }

    public function copy(Request $request, StatutoryRuleSet $set): RedirectResponse
    {
        $copy = StatutoryRuleSet::create([
            'name' => $set->name.' (copy)', 'effective_from' => now()->startOfMonth()->addMonthNoOverflow(),
            'status' => StatutoryRuleSet::DRAFT, 'rules' => $set->rules, 'note' => $set->note, 'updated_by' => $request->user()->id,
        ]);
        activity()->performedOn($copy)->causedBy($request->user())->withProperties(['copied_from' => $set->id])->log('Statutory rule set copied to a new draft');

        return back()->with('status', 'Copied as a draft. Edit the rates and the effective date, then activate it.');
    }

    public function activate(Request $request, StatutoryRuleSet $set): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Your password is incorrect.']);

        try {
            if ($set->status !== StatutoryRuleSet::DRAFT) {
                throw new BusinessRuleException('This rule set is already active.');
            }
            StatutoryCalculator::assertValid($set->rules);
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['statutory' => $e->getMessage()]);
        }

        $set->update(['status' => StatutoryRuleSet::ACTIVE, 'activated_by' => $request->user()->id, 'activated_at' => now()]);
        activity()->performedOn($set)->causedBy($request->user())->withProperties(['effective_from' => $set->effective_from->toDateString(), 'rules' => $set->rules])->log('Statutory rule set activated');

        return back()->with('status', 'Activated. Payroll for '.$set->effective_from->format('F Y').' onwards will use these rates, and the set is now locked.');
    }

    public function destroy(Request $request, StatutoryRuleSet $set): RedirectResponse
    {
        if ($set->status !== StatutoryRuleSet::DRAFT) {
            return back()->withErrors(['statutory' => 'An active rule set cannot be deleted.']);
        }
        activity()->performedOn($set)->causedBy($request->user())->log('Draft statutory rule set deleted');
        $set->delete();

        return back()->with('status', 'Draft deleted.');
    }

    private function save(Request $request, StatutoryRuleSet $set): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'effective_from' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'bands' => ['required', 'array', 'max:10'],
            'bands.*.width' => ['nullable', 'string', 'max:20'],
            'bands.*.rate' => ['nullable', 'string', 'max:6'],
            'rent_relief_pct' => ['required', 'string', 'max:6'],
            'rent_relief_cap' => ['required', 'string', 'max:20'],
            'pension_employee_pct' => ['required', 'string', 'max:6'],
            'pension_employer_pct' => ['required', 'string', 'max:6'],
            'nhf_pct' => ['required', 'string', 'max:6'],
            'paye_day' => ['required', 'integer'],
            'nhf_day' => ['required', 'integer'],
            'pension_working_days' => ['required', 'integer'],
        ]);

        try {
            $bands = [];
            foreach ($data['bands'] as $row) {
                if (trim((string) ($row['rate'] ?? '')) === '' && trim((string) ($row['width'] ?? '')) === '') {
                    continue; // empty slot
                }
                $width = trim((string) ($row['width'] ?? ''));
                $bands[] = ['width' => $width === '' ? null : Money::fromNaira($width), 'rate_bps' => self::bpsFromPercent((string) ($row['rate'] ?? ''))];
            }

            $rules = [
                'paye' => [
                    'bands' => $bands,
                    'rent_relief_bps' => self::bpsFromPercent($data['rent_relief_pct']),
                    'rent_relief_cap' => Money::fromNaira($data['rent_relief_cap']),
                ],
                'pension' => ['employee_bps' => self::bpsFromPercent($data['pension_employee_pct']), 'employer_bps' => self::bpsFromPercent($data['pension_employer_pct'])],
                'nhf' => ['employee_bps' => self::bpsFromPercent($data['nhf_pct'])],
                'remit' => ['paye_day' => (int) $data['paye_day'], 'nhf_day' => (int) $data['nhf_day'], 'pension_working_days' => (int) $data['pension_working_days']],
            ];
            StatutoryCalculator::assertValid($rules);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['statutory' => 'Enter amounts in naira (e.g. 800,000) and rates as percentages (e.g. 15 or 2.5).']);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->withErrors(['statutory' => $e->getMessage()]);
        }

        $isNew = ! $set->exists;
        $set->fill([
            'name' => $data['name'], 'effective_from' => $data['effective_from'], 'note' => $data['note'] ?? null,
            'rules' => $rules, 'status' => StatutoryRuleSet::DRAFT, 'updated_by' => $request->user()->id,
        ])->save();
        activity()->performedOn($set)->causedBy($request->user())->log($isNew ? 'Statutory rule set drafted' : 'Statutory rule set draft edited');

        return back()->with('status', 'Draft saved. Nothing uses it until you activate it.');
    }

    /** "2.5" -> 250 basis points. Strict, so a typo cannot silently become a different rate. */
    public static function bpsFromPercent(string $input): int
    {
        $clean = trim(str_replace('%', '', $input));
        if (! preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', $clean, $m)) {
            throw new InvalidArgumentException('Invalid percentage.');
        }

        return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }
}
