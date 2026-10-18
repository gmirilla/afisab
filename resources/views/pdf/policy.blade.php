@php use App\Services\Policy\PolicyDocumentService as Doc; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $company }} · Mudharabah Investment Agreement · {{ $policy_number }}</title>
<style>
    @page { margin: 64px 56px 72px 56px; }
    @page :first { margin: 0; }
    body { font-family: 'DejaVu Serif', serif; font-size: 10.5px; line-height: 1.55; color: #1f2937; margin: 0; }
    h1, h2 { color: #08532a; }
    h2 { font-size: 12.5px; margin: 16px 0 4px; letter-spacing: 0.3px; }
    p { margin: 0 0 6px; }
    ul { margin: 2px 0 8px 0; padding-left: 20px; }
    li { margin-bottom: 3px; }
    .center { text-align: center; }
    .gold { color: #a8861b; }
    .page-break { page-break-before: always; }

    /* ---- cover ---- */
    .cover { position: relative; width: 794px; height: 1118px; page-break-after: always; overflow: hidden; background: #ffffff; }
    .band-top { position: absolute; top: 0; left: 0; width: 794px; height: 190px; background: #08532a; border-bottom: 7px solid #c9a227; }
    .band-bottom { position: absolute; bottom: 0; left: 0; width: 794px; height: 170px; background: #08532a; border-top: 7px solid #c9a227; }
    .frame { position: absolute; top: 34px; left: 34px; width: 722px; height: 1048px; border: 2px solid #c9a227; }
    .cover-body { position: absolute; top: 215px; left: 0; width: 794px; text-align: center; }
    .cover-body img { height: 300px; }
    .rule { width: 340px; margin: 14px auto; border-top: 2px solid #c9a227; }
    .slogan { font-family: 'DejaVu Sans', sans-serif; font-style: italic; font-size: 15px; color: #08532a; }
    .title { font-family: 'DejaVu Sans', sans-serif; font-weight: bold; font-size: 30px; line-height: 1.15; color: #08532a; margin: 6px 0; letter-spacing: 0.5px; }
    .pill { display: inline-block; background: #08532a; color: #ffffff; font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; letter-spacing: 1.4px; padding: 7px 26px; border-radius: 7px; }
    .values { position: absolute; bottom: 36px; left: 60px; width: 674px; border-collapse: collapse; font-family: 'DejaVu Sans', sans-serif; }
    .values td { width: 20%; text-align: center; color: #ffffff; font-size: 8.5px; letter-spacing: 0.8px; padding: 0 6px; border-right: 1px solid #c9a227; }
    .values td:last-child { border-right: 0; }
    .values b { color: #e6c35c; font-size: 9px; display: block; }

    /* ---- the certificate page ---- */
    .names td { padding: 6px 0; }
    .certno { text-align: center; font-family: 'DejaVu Sans', sans-serif; font-size: 17px; font-weight: bold; color: #08532a; margin-top: 34px; }

    /* ---- schedule ---- */
    table.schedule { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.schedule th, table.schedule td { border: 1px solid #444; padding: 7px 9px; vertical-align: top; font-size: 10px; }
    table.schedule th { width: 38%; text-align: left; font-family: 'DejaVu Sans', sans-serif; font-size: 8.8px; letter-spacing: 0.4px; }

    /* ---- execution ---- */
    .sign td { padding: 3px 0; font-size: 10.5px; }
    .line { border-bottom: 1px solid #555; display: inline-block; width: 260px; height: 14px; }
    .sigimg { height: 62px; }
    .small { font-size: 9px; color: #6b7280; }
    .qr { float: right; width: 110px; text-align: center; font-size: 8px; color: #6b7280; }
    .qr img { width: 92px; height: 92px; }
</style>
</head>
<body>

{{-- 1. Cover --}}
<div class="cover">
    <div class="band-top"></div>
    <div class="band-bottom"></div>
    <div class="frame"></div>
    <div class="cover-body">
        @if($logo)<img src="{{ $logo }}" alt="{{ $company }}">@else<div class="title">{{ $company }}</div>@endif
        <div class="rule"></div>
        <div class="slogan">{{ str_replace('Trust, ', 'Trust. ', $slogan) }}</div>
        <div class="rule" style="width: 600px; margin-top: 30px;"></div>
        <div class="title">MUDHARABAH<br>INVESTMENT AGREEMENT</div>
        <div class="rule" style="width: 600px;"></div>
        <div style="margin-top: 22px;"><span class="pill">READ THIS DOCUMENT CAREFULLY</span></div>
    </div>
    <table class="values"><tr>
        <td><b>AGRICULTURE</b>FOCUSED</td><td><b>PARTNERSHIP</b>DRIVEN</td><td><b>PROFITABLE</b>INVESTMENTS</td><td><b>TRANSPARENCY</b>&amp; INTEGRITY</td><td><b>SUSTAINABLE</b>GROWTH</td>
    </tr></table>
</div>

{{-- 2. Parties and certificate number --}}
<div>
    <p class="center" style="margin-top: 70px; font-size: 13px; color:#08532a;"><strong>MUDHARABAH INVESTMENT CERTIFICATE</strong></p>
    <p class="center" style="margin: 18px 0;">BETWEEN</p>
    <p><strong>{{ $company }}</strong> (Hereinafter referred to as the “Mudarib” or “Fund Manager”)</p>
    <p class="center" style="margin: 14px 0;">AND</p>
    <p><strong>{{ $investor_name }}</strong> (Hereinafter referred to as the “Rabb-ul-Maal” or “Investor”)</p>
    <p style="margin-top: 20px;">This Mudharabah Investment Certificate (“Agreement”) is made on this {{ $made_day }} day of {{ $made_month }}, {{ $made_year }}.</p>
    <p class="certno">CERTIFICATE NO: {{ $policy_number }}</p>
    @if($qr)
        <div class="qr" style="margin-top: 30px;"><img src="{{ $qr }}" alt="QR"><br>Scan to verify this policy</div>
    @endif
</div>

@foreach($sections as $section)
    <div @if(!empty($section['break']) || $section['no'] === '1') class="page-break" @endif>
        <h2>{{ $section['no'] }}. {{ $section['title'] }}</h2>
        @foreach($section['blocks'] as $block)
            @if(isset($block['p']))
                <p>{!! Doc::inline($block['p']) !!}</p>
            @elseif(isset($block['ul']))
                <ul>@foreach($block['ul'] as $item)<li>{!! Doc::inline($item) !!}</li>@endforeach</ul>
            @elseif(isset($block['checks']))
                <div style="margin: 2px 0 8px 12px;">@foreach($block['checks'] as [$label, $on])<div>{{ $on ? '☒' : '☐' }}&nbsp; {{ $label }}</div>@endforeach</div>
            @elseif(isset($block['schedule']))
                <div class="page-break">
                    <h2>2.2 SCHEDULE</h2>
                    <table class="schedule">
                        @foreach($schedule as [$label, $lines])
                            <tr><th>{{ $label }}</th><td>@foreach($lines as $line)<div>{{ $line }}</div>@endforeach</td></tr>
                        @endforeach
                    </table>
                </div>
            @elseif(isset($block['execution']))
                <table class="sign" style="margin-top: 14px; width: 100%;">
                    <tr><td colspan="2"><strong>INVESTOR (RABB-UL-MAAL)</strong></td></tr>
                    <tr><td style="width: 110px;">Name:</td><td>{{ $investor_name }}</td></tr>
                    <tr><td>Address:</td><td>{{ $investor_address }}</td></tr>
                    <tr><td>Signature:</td><td>
                        @if($accepted)
                            <strong>Accepted electronically</strong> on {{ $accepted['at'] }}<br><span class="small">Recorded against policy {{ $policy_number }}@if(!empty($accepted['ip'])) from IP address {{ $accepted['ip'] }}@endif</span>
                        @else
                            <span class="line"></span>
                        @endif
                    </td></tr>
                    <tr><td>Date:</td><td>@if($accepted){{ \Illuminate\Support\Str::before($accepted['at'], ',') }}@else<span class="line" style="width: 160px;"></span>@endif</td></tr>
                </table>

                <p class="small" style="margin-top: 14px;">Witnesses (for a printed, hand-signed copy):</p>
                <table class="sign" style="width: 100%;">
                    <tr><td style="width: 50%;">WITNESS 1<br>Name: <span class="line" style="width: 190px;"></span><br>Signature: <span class="line" style="width: 170px;"></span><br>Date: <span class="line" style="width: 190px;"></span></td>
                        <td>WITNESS 2<br>Name: <span class="line" style="width: 190px;"></span><br>Signature: <span class="line" style="width: 170px;"></span><br>Date: <span class="line" style="width: 190px;"></span></td></tr>
                </table>

                <p style="margin-top: 22px;">In witness whereof, this Certificate has been signed on behalf of {{ $company }} (Mudarib) this {{ $made_day }} day of {{ $made_month }} {{ $made_year }}.</p>
                <div style="margin-top: 8px; height: 68px;">
                    @if(!empty($signatory['image']))<img class="sigimg" src="{{ $signatory['image'] }}" alt="Signature">@endif
                </div>
                <div style="border-top: 1px solid #555; width: 250px; padding-top: 3px;">
                    <strong>{{ $signatory['name'] ?? '' }}</strong><br>{{ $signatory['title'] ?? 'Managing Director / CEO' }}<br>
                    <strong style="font-size: 9px;">FOR: {{ $company }}</strong>
                </div>
            @endif
        @endforeach
    </div>
@endforeach
</body>
</html>
