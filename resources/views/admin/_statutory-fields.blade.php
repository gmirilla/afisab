@php
    $pct = fn (?int $bps) => $bps === null ? '' : rtrim(rtrim(number_format($bps / 100, 2, '.', ''), '0'), '.');
    $naira = fn (?int $kobo) => $kobo === null ? '' : number_format($kobo / 100, 2, '.', '');
    $bands = $r['paye']['bands'] ?? [];
@endphp
<div class="grid gap-3 md:grid-cols-4">
    <x-field name="name" label="Name" :value="$s?->name" class="md:col-span-2" required />
    <x-field name="effective_from" label="Effective from (first month it applies)" type="date" :value="$s?->effective_from?->format('Y-m-d')" required />
    <x-field name="note" label="Note (source, who confirmed)" :value="$s?->note" />
</div>
<p class="mb-1 mt-4 text-sm font-bold text-gray-700">PAYE bands, per year. Leave the width of the last band empty ("the rest").</p>
<div class="grid max-w-xl grid-cols-2 gap-x-3 gap-y-1">
    @for($i = 0; $i < 8; $i++)
        <input name="bands[{{ $i }}][width]" value="{{ old("bands.$i.width", isset($bands[$i]) ? $naira($bands[$i]['width']) : '') }}" placeholder="Band width (₦)" inputmode="decimal" class="rounded border border-gray-300 px-3 py-1.5 text-sm">
        <input name="bands[{{ $i }}][rate]" value="{{ old("bands.$i.rate", isset($bands[$i]) ? $pct($bands[$i]['rate_bps']) : '') }}" placeholder="Rate (%)" inputmode="decimal" class="rounded border border-gray-300 px-3 py-1.5 text-sm">
    @endfor
</div>
<div class="mt-4 grid gap-3 md:grid-cols-4">
    <x-field name="rent_relief_pct" label="Rent relief (% of rent)" :value="$pct($r['paye']['rent_relief_bps'] ?? null)" inputmode="decimal" required />
    <x-field name="rent_relief_cap" label="Rent relief cap (₦ / year)" :value="$naira($r['paye']['rent_relief_cap'] ?? null)" inputmode="decimal" required />
    <x-field name="pension_employee_pct" label="Pension: employee (%)" :value="$pct($r['pension']['employee_bps'] ?? null)" inputmode="decimal" required />
    <x-field name="pension_employer_pct" label="Pension: employer (%)" :value="$pct($r['pension']['employer_bps'] ?? null)" inputmode="decimal" required />
    <x-field name="nhf_pct" label="NHF: employee (% of basic)" :value="$pct($r['nhf']['employee_bps'] ?? null)" inputmode="decimal" required />
    <x-field name="paye_day" label="PAYE due: day of next month" type="number" :value="$r['remit']['paye_day'] ?? 10" required />
    <x-field name="nhf_day" label="NHF due: day of next month" type="number" :value="$r['remit']['nhf_day'] ?? 30" required />
    <x-field name="pension_working_days" label="Pension due: working days after payday" type="number" :value="$r['remit']['pension_working_days'] ?? 7" required />
</div>
