<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\DistributionLine;
use App\Models\DistributionRun;
use App\Services\Performance\DistributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DistributionController extends Controller
{
    public function index(): View
    {
        return view('admin.distributions', [
            'runs' => DistributionRun::orderByDesc('period')->get(),
            'next' => DistributionService::nextSchedule(),
        ]);
    }

    public function show(DistributionRun $run): View
    {
        return view('admin.distribution', [
            'run' => $run,
            'lines' => $run->lines()->with('investment.user')->get(),
        ]);
    }

    public function calculate(Request $request, DistributionService $distributions): RedirectResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);

        return $this->attempt(fn () => $distributions->calculate(Carbon::createFromFormat('Y-m', $data['month']), $request->user()), 'Run calculated.');
    }

    public function recalculate(Request $request, DistributionRun $run, DistributionService $distributions): RedirectResponse
    {
        return $this->attempt(fn () => $distributions->calculate(Carbon::createFromFormat('Y-m', $run->period), $request->user(), notify: false), 'Run recalculated.');
    }

    public function approve(Request $request, DistributionRun $run, DistributionService $distributions): RedirectResponse
    {
        return $this->attempt(fn () => $distributions->approve($run, $request->user()), 'Run approved. Investors can now be paid.');
    }

    public function pay(Request $request, DistributionRun $run, DistributionService $distributions): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:64']]);

        return $this->attempt(fn () => $distributions->markAllPaid($run, $data['reference'], $request->user()), 'All payable lines marked as paid.');
    }

    public function payLine(Request $request, DistributionLine $line, DistributionService $distributions): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:64']]);

        return $this->attempt(fn () => $distributions->markLinePaid($line, $data['reference'], $request->user()), 'Marked as paid.');
    }

    public function export(DistributionRun $run, DistributionService $distributions): StreamedResponse
    {
        abort_if($run->status === DistributionRun::CALCULATED, 422, 'Approve the run before exporting payments.');
        $rows = $distributions->exportRows($run);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "profit-distribution-{$run->period}.csv", ['Content-Type' => 'text/csv']);
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['distribution' => $e->getMessage()]);
        }

        return back()->with('status', $success);
    }
}
