<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #0b4f2a; margin: 0; font-size: 9pt; }
    .abs { position: absolute; }
    table { border-collapse: collapse; }
    .contact td { font-size: 6.1pt; line-height: 1.25; color: #0b4f2a; vertical-align: top; padding: 0 0 2.5pt; }
    .contact td.i { width: 14pt; }
    .contact img { width: 9pt; height: 9pt; }
    .meta { font-size: 8.4pt; }
    .lead { text-align: center; font-size: 8.8pt; line-height: 1.35; color: #14472e; }
    .lead .amt { font-size: 13.5pt; font-weight: bold; line-height: 1.3; }
    .lead .words { font-size: 9.2pt; font-weight: bold; }
    table.rows { width: 380pt; border: 0.75pt solid #d9b441; }
    table.rows td { height: 17.5pt; padding: 0; vertical-align: middle; border-bottom: 0.75pt solid #e8d38a; }
    table.rows td.ic { width: 30pt; background: #0b4f2a; text-align: center; border-bottom: 0.75pt solid #2d7048; }
    table.rows td.ic img { width: 12pt; height: 12pt; }
    table.rows td.k { width: 116pt; padding-left: 6pt; background: #0b4f2a; color: #fff; font-weight: bold; font-size: 7pt; border-bottom: 0.75pt solid #2d7048; }
    table.rows td.v { background: #fffdf5; font-size: 7.8pt; color: #14472e; padding-left: 8pt; }
    table.rows tr.last td { border-bottom: 0; }
    .thanks { text-align: center; font-size: 8pt; line-height: 1.5; color: #14472e; }
    .small { font-size: 7.6pt; font-weight: bold; color: #14472e; }
    .line { border-top: 0.75pt solid #0b4f2a; }
    .cap { font-size: 7.4pt; color: #14472e; text-align: center; }
    .slogan { text-align: center; font-family: 'DejaVu Serif', serif; font-style: italic; font-size: 9.5pt; color: #fff; }
</style>
</head>
<body>
    {{-- header: logo lock-up on the left, contact lines on the right --}}
    <img class="abs" src="{{ $assets['lockup'] }}" alt="{{ $company }}" style="left:14pt; top:10pt; width:262pt; height:61pt;">
    <div class="abs" style="left:287pt; top:14pt; height:80pt; border-left:0.75pt solid #0b4f2a;"></div>
    <table class="abs contact" style="left:297pt; top:14pt; width:118pt;">
        @if($details['address'])<tr><td class="i"><img src="{{ $assets['pin'] }}" alt=""></td><td>@foreach($details['address'] as $line){{ $line }}@if(! $loop->last)<br>@endif @endforeach</td></tr>@endif
        @if($details['phones'])<tr><td class="i"><img src="{{ $assets['phone'] }}" alt=""></td><td>@foreach($details['phones'] as $p){{ $p }}@if(! $loop->last)<br>@endif @endforeach</td></tr>@endif
        @if($details['emails'])<tr><td class="i"><img src="{{ $assets['mail'] }}" alt=""></td><td>@foreach($details['emails'] as $e){{ $e }}@if(! $loop->last)<br>@endif @endforeach</td></tr>@endif
        @if($details['website'])<tr><td class="i"><img src="{{ $assets['globe'] }}" alt=""></td><td>{{ $details['website'] }}</td></tr>@endif
    </table>

    <table class="abs meta" style="left:15pt; top:106pt; width:390pt;"><tr>
        <td>Receipt No.:&nbsp; <strong>{{ $receipt_number }}</strong></td>
        <td style="text-align:right">Date of Issue:&nbsp; <strong>{{ $issued_on }}</strong></td>
    </tr></table>

    <img class="abs" src="{{ $assets['banner'] }}" alt="Investment Receipt" style="left:0; top:121pt; width:420pt; height:54pt;">

    {{-- opening sentence, then the table of details --}}
    <div class="abs" style="left:20pt; top:182pt; width:380pt;">
        <div class="lead">
            This is to confirm that <strong>{{ $company }}</strong> has received the sum of<br>
            <span class="amt">{{ $amount }}</span> <span class="words">({{ $amount_words }})</span><br>
            from <strong>{{ $investor_name }}</strong> as an investment in the company<br>
            for a period of {{ $period_sentence }}.
        </div>
        <table class="rows" style="margin-top:7pt;">
            <tr><td class="ic"><img src="{{ $assets['user'] }}" alt=""></td><td class="k">Investor's Name</td><td class="v">:&nbsp; <strong>{{ $investor_name }}</strong></td></tr>
            <tr><td class="ic"><img src="{{ $assets['coins'] }}" alt=""></td><td class="k">Amount Received</td><td class="v">:&nbsp; <strong>{{ $amount }}</strong> ({{ $amount_words }})</td></tr>
            <tr><td class="ic"><img src="{{ $assets['naira'] }}" alt=""></td><td class="k">Amount in Words</td><td class="v">:&nbsp; {{ $amount_words }}</td></tr>
            <tr><td class="ic"><img src="{{ $assets['calendar'] }}" alt=""></td><td class="k">Investment Period</td><td class="v">:&nbsp; {{ $period }}</td></tr>
            <tr><td class="ic"><img src="{{ $assets['target'] }}" alt=""></td><td class="k">Investment Start Date</td><td class="v">:&nbsp; {{ $start }}</td></tr>
            <tr><td class="ic"><img src="{{ $assets['document'] }}" alt=""></td><td class="k">Investment Maturity Date</td><td class="v">:&nbsp; {{ $maturity }}</td></tr>
            <tr><td class="ic"><img src="{{ $assets['wallet'] }}" alt=""></td><td class="k">Investment Fund Received</td><td class="v">:&nbsp; {{ $received }}</td></tr>
            <tr><td class="ic"><img src="{{ $assets['calendar'] }}" alt=""></td><td class="k">Profit Payment Frequency</td><td class="v">:&nbsp; {{ $frequency }}</td></tr>
            <tr class="last"><td class="ic"><img src="{{ $assets['document'] }}" alt=""></td><td class="k">Payment Reference</td><td class="v">:&nbsp; {{ $payment_reference }}</td></tr>
        </table>
        <div class="thanks" style="margin-top:5pt;">We appreciate your trust and confidence in <strong>{{ $company }}</strong>.<br>We look forward to a mutually beneficial partnership.</div>
    </div>

    {{-- footer artwork first, so the signature block and stamp sit above it --}}
    <img class="abs" src="{{ $assets['footer'] }}" alt="" style="left:0; top:545pt; width:420pt; height:95pt;">
    <div class="abs slogan" style="left:0; top:598pt; width:420pt;">{{ $slogan }}</div>

    {{-- signature, stamp --}}
    <div class="abs small" style="left:15pt; top:486pt;">For: {{ $company }}</div>
    @if($signatory)<img class="abs" src="{{ $signatory }}" alt="" style="left:34pt; top:497pt; height:36pt;">@endif
    <div class="abs line" style="left:15pt; top:535pt; width:135pt;"></div>
    <div class="abs cap" style="left:15pt; top:539pt; width:135pt;">Authorized Signatory</div>
    @if($stamp)<img class="abs" src="{{ $stamp }}" alt="" style="left:170pt; top:487pt; width:80pt; height:80pt;">@endif
    <div class="abs line" style="left:282pt; top:535pt; width:122pt;"></div>
    <div class="abs cap" style="left:282pt; top:539pt; width:122pt;">Company Stamp</div>
</body>
</html>
