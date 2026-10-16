<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AttemptsActions;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PensionAdministrator;
use App\Models\StatutoryRuleSet;
use App\Models\PayrollRun;
use App\Services\Hr\PayrollService;
use App\Support\Csv;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrController extends Controller
{
    use AttemptsActions;

    public function employees(): View
    {
        return view('admin.hr.employees', [
            'employees' => Employee::with('pensionAdministrator')->orderByDesc('is_active')->orderBy('name')->get(),
            'administrators' => PensionAdministrator::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function saveEmployee(Request $request, ?Employee $employee = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:32'],
            'position' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'hire_date' => ['nullable', 'date'],
            'basic_salary' => ['required', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'digits:10'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'housing_allowance' => ['nullable', 'string', 'max:20'],
            'transport_allowance' => ['nullable', 'string', 'max:20'],
            'annual_rent' => ['nullable', 'string', 'max:20'],
            'pension_administrator_id' => ['nullable', Rule::exists('pension_administrators', 'id')->where('is_active', true)],
            'rsa_pin' => ['nullable', 'string', 'max:20'],
            'nhf_number' => ['nullable', 'string', 'max:32'],
            'tax_state' => ['nullable', 'string', 'max:60'],
        ]);

        // Money fields: blank keeps the employee's current value (or zero for a new one) so a partial form never wipes pay.
        foreach (['basic_salary', 'housing_allowance', 'transport_allowance', 'annual_rent'] as $field) {
            if (($data[$field] ?? '') === '' && $field !== 'basic_salary') {
                $data[$field] = $employee?->{$field} ?? 0;

                continue;
            }
            try {
                $data[$field] = Money::fromNaira($data[$field]);
            } catch (\InvalidArgumentException) {
                return back()->withInput()->withErrors([$field => 'Enter a valid amount in naira.']);
            }
        }
        $data['is_active'] = $request->boolean('is_active', ! $employee);
        // Statutory switches: the forms send a hidden 0 so an unticked box really switches it off; absent = unchanged.
        $data['pension_exempt'] = $request->boolean('pension_exempt', $employee?->pension_exempt ?? false);
        $data['paye_exempt'] = $request->boolean('paye_exempt', $employee?->paye_exempt ?? false);
        $data['nhf_applies'] = $request->boolean('nhf_applies', $employee?->nhf_applies ?? true);

        $saved = $employee ? tap($employee)->update($data) : Employee::create($data);
        activity()->performedOn($saved)->causedBy($request->user())->log('Employee record saved');

        return back()->with('status', 'Employee saved.');
    }

    public function payroll(Request $request): View
    {
        $period = $request->query('period', now()->format('Y-m'));

        return view('admin.hr.payroll', [
            'ruleSet' => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) ? StatutoryRuleSet::forPeriod($period) : null,
            'runs' => PayrollRun::orderByDesc('period')->get(),
            'period' => $period,
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(),
            'adjustments' => PayrollAdjustment::with('employee')->where('period', $period)->orderBy('employee_id')->get(),
        ]);
    }

    public function addAdjustment(Request $request, PayrollService $payroll): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'period' => ['required', 'date_format:Y-m'],
            'type' => ['required', Rule::in(PayrollAdjustment::TYPES)],
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'string', 'max:20'],
        ]);

        return $this->attempt(
            fn () => $payroll->addAdjustment(Employee::findOrFail($data['employee_id']), $data['period'], $data['type'], $data['description'], Money::fromNaira($data['amount']), $request->user()),
            'Adjustment added.',
        );
    }

    public function removeAdjustment(Request $request, PayrollAdjustment $adjustment, PayrollService $payroll): RedirectResponse
    {
        return $this->attempt(fn () => $payroll->removeAdjustment($adjustment, $request->user()), 'Adjustment removed.');
    }

    public function prepare(Request $request, PayrollService $payroll): RedirectResponse
    {
        $data = $request->validate(['period' => ['required', 'date_format:Y-m']]);

        return $this->attempt(fn () => $payroll->prepare($data['period'], $request->user()), 'Payroll prepared for approval.');
    }

    public function show(PayrollRun $run): View
    {
        return view('admin.hr.payroll-run', ['run' => $run, 'lines' => $run->lines()->with('employee')->get()]);
    }

    public function approve(Request $request, PayrollRun $run, PayrollService $payroll): RedirectResponse
    {
        return $this->attempt(fn () => $payroll->approve($run, $request->user()), 'Payroll approved.');
    }

    public function pay(Request $request, PayrollRun $run, PayrollService $payroll): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:64']]);

        return $this->attempt(fn () => $payroll->pay($run, $data['reference'], $request->user()), 'Payroll paid.');
    }

    public function export(PayrollRun $run, PayrollService $payroll): StreamedResponse
    {
        abort_if($run->status === PayrollRun::DRAFT, 422, 'Approve the payroll before exporting payments.');

        return Csv::download("payroll-{$run->period}.csv", $payroll->exportRows($run));
    }
}
