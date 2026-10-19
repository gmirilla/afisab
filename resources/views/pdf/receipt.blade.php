<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; margin: 0; font-size: 9px; }
    .page { padding: 26px 30px 0; }
    table { border-collapse: collapse; width: 100%; }
    .head td { vertical-align: top; }
    .logo { height: 54px; }
    .brand { color: #08532a; font-size: 16px; font-weight: bold; }
    .contact { text-align: right; font-size: 7.5px; color: #4b5563; line-height: 1.5; }
    .rule { border-top: 2px solid #08532a; margin: 8px 0 10px; }
    .meta td { font-size: 9px; padding: 0; }
    .meta .r { text-align: right; }
    .pill { text-align: center; margin: 12px 0 10px; }
    .pill span { background: #08532a; color: #fff; font-weight: bold; letter-spacing: 1.5px; font-size: 11px; padding: 5px 22px; }
    .lead { font-size: 9.5px; line-height: 1.55; margin: 0 0 8px; }
    table.rows td { border: 1px solid #d1d5db; padding: 5px 7px; font-size: 9px; vertical-align: top; }
    table.rows td.k { width: 36%; background: #f3f4f6; font-weight: bold; color: #08532a; }
    .sign { margin-top: 14px; }
    .sign td { vertical-align: bottom; }
    .sigimg { height: 38px; }
    .sigline { border-top: 1px solid #6b7280; width: 150px; padding-top: 2px; font-size: 8px; color: #4b5563; }
    .stamp { height: 74px; }
    .footer { position: absolute; left: 0; right: 0; bottom: 0; background: #08532a; color: #fff; text-align: center; font-size: 8px; padding: 7px 0; letter-spacing: 0.5px; }
    .small { font-size: 7px; color: #6b7280; text-align: center; margin-top: 8px; }
</style>
</head>
<body>
<div class="page">
    <table class="head"><tr>
        <td style="width:45%">@if($logo)<img class="logo" src="{{ $logo }}" alt="{{ $company }}">@else<div class="brand">{{ $company }}</div>@endif</td>
        <td class="contact">
            @foreach($details['address'] as $line){{ $line }}<br>@endforeach
            @if($details['phones']){{ implode(' / ', $details['phones']) }}<br>@endif
            @if($details['emails']){{ implode(' / ', $details['emails']) }}<br>@endif
            @if($details['website']){{ $details['website'] }}@endif
        </td>
    </tr></table>
    <div class="rule"></div>

    <table class="meta"><tr>
        <td><strong>Receipt No.:</strong> {{ $receipt_number }}</td>
        <td class="r"><strong>Date of Issue:</strong> {{ $issued_on }}</td>
    </tr></table>

    <div class="pill"><span>INVESTMENT RECEIPT</span></div>

    <p class="lead">This is to confirm that <strong>{{ $company }}</strong> has received the sum of <strong>{{ $amount }}</strong> ({{ $amount_words }}) from <strong>{{ $investor_name }}</strong> as an investment in the company for a period of {{ $period_sentence }}.</p>

    <table class="rows">
        <tr><td class="k">Investor's Name</td><td>{{ $investor_name }}</td></tr>
        <tr><td class="k">Amount Received</td><td>{{ $amount }}</td></tr>
        <tr><td class="k">Amount in Words</td><td>{{ $amount_words }}</td></tr>
        <tr><td class="k">Investment Period</td><td>{{ $period }}</td></tr>
        <tr><td class="k">Investment Start Date</td><td>{{ $start }}</td></tr>
        <tr><td class="k">Investment Maturity Date</td><td>{{ $maturity }}</td></tr>
        <tr><td class="k">Investment Fund Received</td><td>{{ $received }}</td></tr>
        <tr><td class="k">Profit Payment Frequency</td><td>{{ $frequency }}</td></tr>
        <tr><td class="k">Payment Reference</td><td>{{ $payment_reference }}</td></tr>
    </table>

    <table class="sign"><tr>
        <td>
            @if($signatory)<img class="sigimg" src="{{ $signatory }}" alt=""><br>@else<div style="height:38px"></div>@endif
            <div class="sigline">Authorized Signatory</div>
        </td>
        <td style="text-align:right">@if($stamp)<img class="stamp" src="{{ $stamp }}" alt="">@endif</td>
    </tr></table>

    <div class="small">Keep this receipt with your investment agreement. Returns, if any, are a share of actual trading profit and are not guaranteed.</div>
</div>
<div class="footer">{{ $slogan }}</div>
</body>
</html>
