@php use App\Support\Money; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
    h1 { color: #08532a; font-size: 18px; margin: 0; }
    h2 { color: #08532a; font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #c9a227; padding-bottom: 3px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; background: #08532a; color: #fff; padding: 4px 6px; font-size: 10px; }
    td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }
    .r { text-align: right; }
    .muted { color: #6b7280; }
    .note { margin-top: 14px; font-size: 9px; color: #6b7280; }
</style>
</head>
<body>
<h1>AFISAB NIG. LTD — Investment statement</h1>
<p class="muted">Generated {{ $generatedAt->format('d M Y H:i') }}</p>

<table>
    <tr><td>Holder</td><td><strong>{{ $investment->user->name }}</strong></td><td>Reference</td><td><strong>{{ $investment->reference }}</strong></td></tr>
    <tr><td>Plan</td><td>{{ $investment->plan->name }} ({{ $investment->duration_months }} months)</td><td>Status</td><td>{{ $investment->status->label() }}</td></tr>
    <tr><td>Start</td><td>{{ $investment->start_date?->format('d M Y') ?? '—' }}</td><td>Maturity</td><td>{{ $investment->maturity_date?->format('d M Y') ?? '—' }}</td></tr>
</table>

<h2>Position</h2>
<table>
    <tr><td>Principal</td><td class="r">{{ Money::format($position['principal']) }}</td></tr>
    <tr><td>Profit received</td><td class="r">{{ Money::format($position['paid']) }}</td></tr>
    <tr><td>Profit approved, awaiting payment</td><td class="r">{{ Money::format($position['payable']) }}</td></tr>
    <tr><td><strong>Current value</strong></td><td class="r"><strong>{{ Money::format($position['current_value']) }}</strong></td></tr>
</table>
@if($position['pending_entries'] > 0)
    <p class="muted">{{ $position['pending_entries'] }} trading result(s) recorded and not yet included in an approved monthly distribution; they are not included in the value above.</p>
@endif

<h2>Payments</h2>
<table>
    <tr><th>Reference</th><th>Method</th><th>Status</th><th class="r">Amount</th></tr>
    @forelse($investment->payments as $p)
        <tr><td>{{ $p->reference }}</td><td>{{ ucfirst($p->method->value) }}</td><td>{{ $p->status->label() }}</td><td class="r">{{ Money::format($p->amount) }}</td></tr>
    @empty
        <tr><td colspan="4" class="muted">No payments.</td></tr>
    @endforelse
</table>

<h2>Trading performance</h2>
<table>
    <tr><th>Cycle</th><th>Date</th><th class="r">Capital in cycle</th><th>Included in</th></tr>
    @forelse($entries as $e)
        <tr>
            <td>{{ $e->cycle_reference }}</td><td>{{ $e->recorded_at->format('d M Y') }}</td>
            <td class="r">{{ Money::format($e->capital_allocated) }}</td>
            <td>{{ $e->run ? $e->run->label().' distribution' : 'Next monthly run' }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="muted">No trading cycles have closed for this investment yet.</td></tr>
    @endforelse
</table>

<p class="note">Commodity trading involves risk. Returns are a share of actual trading profit and are not guaranteed.</p>
</body>
</html>
