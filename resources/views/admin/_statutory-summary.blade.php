@php
    use App\Support\Money;
    $pct = fn (int $bps) => rtrim(rtrim(number_format($bps / 100, 2, '.', ''), '0'), '.') ?: '0';
@endphp
<div class="mt-3 grid gap-4 text-sm md:grid-cols-3">
    <div>
        <p class="font-bold">PAYE bands (per year)</p>
        <ul class="mt-1 space-y-0.5">
            @foreach($r['paye']['bands'] as $b)
                <li>{{ $b['width'] === null ? 'The rest' : 'Next '.Money::format($b['width']) }} at <strong>{{ $pct($b['rate_bps']) }}%</strong></li>
            @endforeach
        </ul>
        <p class="mt-1 text-gray-600">Rent relief {{ $pct($r['paye']['rent_relief_bps']) }}% of rent, up to {{ Money::format($r['paye']['rent_relief_cap']) }} a year.</p>
    </div>
    <div>
        <p class="font-bold">Contributions</p>
        <ul class="mt-1 space-y-0.5">
            <li>Pension, employee: <strong>{{ $pct($r['pension']['employee_bps']) }}%</strong></li>
            <li>Pension, employer: <strong>{{ $pct($r['pension']['employer_bps']) }}%</strong></li>
            <li>NHF, employee: <strong>{{ $pct($r['nhf']['employee_bps']) }}%</strong> of basic</li>
        </ul>
        <p class="mt-1 text-gray-600">Pension is on basic + housing + transport.</p>
    </div>
    <div>
        <p class="font-bold">Remit by</p>
        <ul class="mt-1 space-y-0.5">
            <li>PAYE: day {{ $r['remit']['paye_day'] }} of the next month</li>
            <li>Pension: {{ $r['remit']['pension_working_days'] }} working days after payday</li>
            <li>NHF: day {{ $r['remit']['nhf_day'] }} of the next month</li>
        </ul>
    </div>
</div>
