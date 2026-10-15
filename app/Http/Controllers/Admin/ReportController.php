<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportService;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('admin.reports.index');
    }

    public function investors(Request $request, ReportService $reports): View|StreamedResponse
    {
        $rows = $reports->investors($request->query('status'));

        if ($request->query('format') === 'csv') {
            return Csv::download('investors-report.csv', array_merge(
                [['Policy', 'Investor', 'Email', 'Plan', 'Amount (NGN)', 'Status', 'Start', 'Maturity', 'Profit paid (NGN)', 'Profit payable (NGN)']],
                $rows->map(fn ($r) => [$r['reference'], $r['investor'], $r['email'], $r['plan'], $this->n($r['amount']), $r['status'], $r['start'], $r['maturity'], $this->n($r['profit_paid']), $this->n($r['profit_payable'])])->all(),
            ));
        }

        return view('admin.reports.investors', ['rows' => $rows, 'status' => $request->query('status')]);
    }

    public function cycles(Request $request, ReportService $reports): View|StreamedResponse
    {
        $rows = $reports->cycles();

        if ($request->query('format') === 'csv') {
            return Csv::download('trading-cycles-report.csv', array_merge(
                [['Cycle', 'Name', 'Commodity', 'Status', 'Budget (NGN)', 'Purchases (NGN)', 'Expenses (NGN)', 'Sales (NGN)', 'Net profit (NGN)', 'Return on purchases (%)', 'Closed']],
                $rows->map(fn ($r) => [$r['reference'], $r['name'], $r['commodity'], $r['status'], $this->n($r['budget']), $this->n($r['purchases']), $this->n($r['expenses']), $this->n($r['sales']), $r['net_profit'] === null ? '' : $this->n($r['net_profit']), $r['return_bps'] === null ? '' : number_format($r['return_bps'] / 100, 2, '.', ''), $r['closed']])->all(),
            ));
        }

        return view('admin.reports.cycles', ['rows' => $rows]);
    }

    public function trialBalance(Request $request, ReportService $reports): View|StreamedResponse
    {
        $asOf = $this->date($request->query('as_of'), now());
        $report = $reports->trialBalance($asOf);

        if ($request->query('format') === 'csv') {
            return Csv::download('trial-balance-'.$asOf->toDateString().'.csv', array_merge(
                [['Code', 'Account', 'Type', 'Debit (NGN)', 'Credit (NGN)']],
                $report['rows']->map(fn ($r) => [$r['code'], $r['name'], $r['type'], $this->n($r['debit']), $this->n($r['credit'])])->all(),
                [['', 'TOTAL', '', $this->n($report['total_debit']), $this->n($report['total_credit'])]],
            ));
        }

        return view('admin.reports.trial-balance', ['report' => $report, 'asOf' => $asOf]);
    }

    public function profitAndLoss(Request $request, ReportService $reports): View|StreamedResponse
    {
        $from = $this->date($request->query('from'), now()->startOfMonth());
        $to = $this->date($request->query('to'), now());
        $report = $reports->profitAndLoss($from, $to);

        if ($request->query('format') === 'csv') {
            $rows = [['Line', 'Amount (NGN)']];
            foreach ($report['income'] as $r) {
                $rows[] = [$r['name'], $this->n($r['amount'])];
            }
            $rows[] = ['Total income', $this->n($report['total_income'])];
            foreach ($report['expenses'] as $r) {
                $rows[] = [$r['name'], $this->n($r['amount'])];
            }
            $rows[] = ['Total expenses', $this->n($report['total_expenses'])];
            $rows[] = ['Net profit', $this->n($report['net'])];

            return Csv::download("profit-and-loss-{$from->toDateString()}-{$to->toDateString()}.csv", $rows);
        }

        return view('admin.reports.profit-loss', ['report' => $report, 'from' => $from, 'to' => $to]);
    }

    public function distributions(Request $request, ReportService $reports): View|StreamedResponse
    {
        $runs = $reports->distributions();

        if ($request->query('format') === 'csv') {
            return Csv::download('profit-distributions.csv', array_merge(
                [['Month', 'Status', 'Net profit (NGN)', 'Retained (NGN)', 'Investors (NGN)', 'Company (NGN)', 'Pay by']],
                $runs->map(fn ($r) => [$r->period, $r->status, $this->n($r->total_net_profit), $this->n($r->total_retained), $this->n($r->total_investor), $this->n($r->total_company), $r->pay_by->toDateString()])->all(),
            ));
        }

        return view('admin.reports.distributions', ['runs' => $runs]);
    }

    private function n(int $kobo): string
    {
        return number_format($kobo / 100, 2, '.', '');
    }

    private function date(?string $value, Carbon $default): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
