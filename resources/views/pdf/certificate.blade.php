@php use App\Support\Money; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; margin: 0; }
    .frame { margin: 22px; border: 6px double #c9a227; padding: 26px 40px; height: 480px; position: relative; }
    .center { text-align: center; }
    .brand { color: #08532a; font-size: 26px; font-weight: bold; letter-spacing: 2px; }
    .logo { height: 96px; }
    .tag { color: #a8861b; font-style: italic; font-size: 12px; }
    h1 { color: #08532a; font-size: 26px; margin: 4px 0 2px; letter-spacing: 3px; }
    .name { font-size: 28px; color: #08532a; border-bottom: 1px solid #c9a227; display: inline-block; padding: 0 30px 4px; margin: 6px 0; }
    .muted { color: #6b7280; font-size: 11px; }
    table.facts { margin: 8px auto 0; border-collapse: collapse; font-size: 12px; }
    table.facts td { padding: 4px 18px; border-bottom: 1px solid #e5e7eb; }
    table.facts td.k { color: #6b7280; text-align: right; }
    .foot { position: absolute; bottom: 20px; left: 40px; right: 40px; }
    .qr { float: right; text-align: center; font-size: 9px; color: #6b7280; width: 120px; }
    .qr img { width: 96px; height: 96px; }
    .sig { float: left; width: 220px; border-top: 1px solid #6b7280; padding-top: 4px; font-size: 10px; color: #6b7280; margin-top: 40px; }
    .disclaimer { clear: both; font-size: 8px; color: #6b7280; text-align: center; padding-top: 6px; }
</style>
</head>
<body>
<div class="frame">
    <div class="center">
        @if($logo)<img class="logo" src="{{ $logo }}" alt="AFISAB NIG. LTD">@else<div class="brand">AFISAB NIG. LTD</div>@endif
        <div class="tag">Building Trust, Delivering Excellence</div>
        <h1>INVESTMENT CERTIFICATE</h1>
        <div class="muted">This certifies that</div>
        <div class="name">{{ $investment->user->name }}</div>
        <div class="muted">has subscribed to the {{ $investment->plan->name }} commodity trading investment programme</div>
    </div>

    <table class="facts">
        <tr><td class="k">Amount invested</td><td><strong>{{ Money::format($investment->amount) }}</strong></td>
            <td class="k">Certificate no.</td><td><strong>{{ $investment->certificate_number }}</strong></td></tr>
        <tr><td class="k">Duration</td><td>{{ $investment->duration_months }} months</td>
            <td class="k">Reference</td><td>{{ $investment->reference }}</td></tr>
        <tr><td class="k">Start date</td><td>{{ $investment->start_date->format('d F Y') }}</td>
            <td class="k">Maturity date</td><td>{{ $investment->maturity_date->format('d F Y') }}</td></tr>
    </table>

    <div class="foot">
        <div class="qr"><img src="{{ $qr }}" alt="QR"><br>Scan to verify<br>Code: {{ $investment->certificate_token }}</div>
        <div class="sig">Authorised signatory, AFISAB NIG. LTD<br>Issued {{ $investment->certificate_issued_at->format('d F Y') }}</div>
        <div class="disclaimer">
            Participation in agricultural commodity trading involves risk. Returns, if any, are a share of actual trading profit and are not guaranteed.
            Verify this certificate at {{ route('verify.form') }}.
        </div>
    </div>
</div>
</body>
</html>
