@php
    $left = [
        ['Amount Invested', $amount],
        ['Investment Period', $investment->duration_months.' '.($investment->duration_months === 1 ? 'month' : 'months')],
        ['Start Date', $investment->start_date->format('jS \o\f F, Y')],
        ['Maturity Date', $investment->maturity_date->format('jS \o\f F, Y')],
        ['Profit Payment', $frequency],
    ];
    $right = array_values(array_filter([
        ['Certificate No.', $investment->certificate_number],
        $policy_number ? ['Policy No.', $policy_number] : null,
        $receipt_number ? ['Receipt No.', $receipt_number] : null,
        $received_on ? ['Funds Received', $received_on] : null,
        ['Reference', $investment->reference],
    ]));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #0b4f2a; margin: 0; }
    .abs { position: absolute; }
    .c { text-align: center; }
    .brand { color: #0b4f2a; font-size: 24pt; font-weight: bold; letter-spacing: 2pt; }
    table.facts { border-collapse: collapse; border: 0.75pt solid #d9b441; }
    table.facts td { height: 18pt; padding: 0; vertical-align: middle; font-size: 8pt; border-bottom: 0.75pt solid #e8d38a; }
    table.facts td.k { width: 104pt; padding-left: 8pt; background: #0b4f2a; color: #fff; font-weight: bold; font-size: 7.4pt; border-bottom: 0.75pt solid #2d7048; }
    table.facts td.v { background: #fffdf5; padding-left: 8pt; color: #14472e; }
    table.facts tr.last td { border-bottom: 0; }
    .line { border-top: 0.75pt solid #0b4f2a; }
    .cap { font-size: 7.4pt; color: #14472e; text-align: center; }
    .small { font-size: 7.6pt; font-weight: bold; color: #14472e; }
    .slogan { text-align: center; font-family: 'DejaVu Serif', serif; font-style: italic; font-size: 10pt; color: #fff; }
</style>
</head>
<body>
    <div class="abs" style="left:12pt; top:12pt; width:810pt; height:563pt; border:4pt double #c9a227;"></div>

    <div class="abs c" style="left:0; top:26pt; width:842pt;">
        @if($assets['lockup'] ?? '')<img class="lockup" src="{{ $assets['lockup'] }}" alt="{{ $company }}" style="width:250pt; height:58pt;">@else<div class="brand">{{ $company }}</div>@endif
    </div>
    @if($assets['banner-certificate'] ?? '')<img class="abs" src="{{ $assets['banner-certificate'] }}" alt="Investment Certificate" style="left:186pt; top:90pt; width:470pt; height:61pt;">@else<div class="abs c" style="left:0; top:105pt; width:842pt; font-size:20pt; font-weight:bold; letter-spacing:3pt;">INVESTMENT CERTIFICATE</div>@endif

    <div class="abs c" style="left:0; top:160pt; width:842pt; font-size:9pt; font-style: italic; color:#4b5563;">This certifies that</div>
    <div class="abs c" style="left:0; top:174pt; width:842pt;"><span style="font-family:'DejaVu Serif', serif; font-size:23pt; font-style:italic; color:#0b4f2a; border-bottom:1pt solid #c9a227; padding:0 30pt 3pt;">{{ $investor_name }}</span></div>
    <div class="abs c" style="left:60pt; top:213pt; width:722pt; font-size:9pt; color:#14472e;">has subscribed to the <strong>{{ $investment->plan->name }}</strong> commodity trading investment programme<br><span style="font-size:8pt; color:#4b5563;">{{ $amount_words }}</span></div>

    <table class="abs facts" style="left:60pt; top:250pt; width:340pt;">
        @foreach($left as [$k, $v])<tr class="{{ $loop->last ? 'last' : '' }}"><td class="k">{{ $k }}</td><td class="v">{{ $v }}</td></tr>@endforeach
    </table>
    <table class="abs facts" style="left:442pt; top:250pt; width:340pt;">
        @foreach($right as [$k, $v])<tr class="{{ $loop->last ? 'last' : '' }}"><td class="k">{{ $k }}</td><td class="v">{{ $v }}</td></tr>@endforeach
    </table>

    {{-- signature, stamp, verification --}}
    <div class="abs small" style="left:60pt; top:362pt;">For: {{ $company }}</div>
    @if($signatory)<img class="abs" src="{{ $signatory }}" alt="" style="left:80pt; top:376pt; height:42pt;">@endif
    <div class="abs line" style="left:60pt; top:422pt; width:200pt;"></div>
    <div class="abs cap" style="left:60pt; top:426pt; width:200pt;">Authorized Signatory<br>Issued {{ $investment->certificate_issued_at->format('d F Y') }}</div>
    @if($stamp)<img class="abs" src="{{ $stamp }}" alt="" style="left:376pt; top:360pt; width:90pt; height:90pt;">@endif
    <div class="abs c" style="left:582pt; top:356pt; width:200pt;">
        <img src="{{ $qr }}" alt="QR" style="width:76pt; height:76pt;">
        <div style="font-size:7pt; color:#14472e;">Scan to verify this certificate<br>Code: <strong>{{ $investment->certificate_token }}</strong></div>
    </div>

    <div class="abs c" style="left:70pt; top:488pt; width:702pt; font-size:6.4pt; color:#6b7280; line-height:1.4;">
        Participation in agricultural commodity trading involves risk. Returns, if any, are a share of actual trading profit and are not guaranteed.
        Verify this certificate at {{ route('verify.form') }}.
    </div>

    @if($assets['footer-wide'] ?? '')<img class="abs" src="{{ $assets['footer-wide'] }}" alt="" style="left:16pt; top:516pt; width:810pt; height:62.5pt;">@endif
    <div class="abs slogan" style="left:0; top:552pt; width:842pt;">{{ $slogan }}</div>
</body>
</html>
