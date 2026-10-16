<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AttemptsActions;
use App\Http\Controllers\Controller;
use App\Models\StatutoryRemittance;
use App\Services\Hr\RemittanceService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** PAYE, pension and NHF owed after each payroll, and the schedule to send with each payment. */
class RemittanceController extends Controller
{
    use AttemptsActions;

    public function index(): View
    {
        $all = StatutoryRemittance::with(['run', 'administrator', 'remitter'])->orderBy('status')->orderBy('due_date')->orderBy('id')->limit(200)->get();

        return view('admin.hr.remittances', [
            'due' => $all->where('status', StatutoryRemittance::DUE),
            'done' => $all->where('status', StatutoryRemittance::REMITTED)->sortByDesc('remitted_at')->take(50),
        ]);
    }

    public function pay(Request $request, StatutoryRemittance $remittance, RemittanceService $remittances): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:64']]);

        return $this->attempt(fn () => $remittances->remit($remittance, $data['reference'], $request->user()), 'Remittance recorded and posted to the ledger.');
    }

    public function schedule(StatutoryRemittance $remittance, RemittanceService $remittances): StreamedResponse
    {
        $name = strtolower($remittance->kind).'-'.$remittance->run->period.'-'.$remittance->id.'.csv';

        return Csv::download($name, $remittances->scheduleRows($remittance));
    }
}
