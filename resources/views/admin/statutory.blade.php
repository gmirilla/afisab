@php
    use App\Support\Money;
    use App\Models\StatutoryRuleSet;
    $pct = fn (int $bps) => rtrim(rtrim(number_format($bps / 100, 2, '.', ''), '0'), '.');
    $naira = fn (?int $kobo) => $kobo === null ? '' : number_format($kobo / 100, 2, '.', '');
@endphp
<x-layout title="Statutory rates">
    <h1 class="mb-1 text-2xl font-black text-brand-700">PAYE, pension and NHF rates</h1>
    <p class="mb-4 text-sm text-gray-600">Rates are kept as versions. A new law is a <strong>new set with a later effective date</strong>; a set that has been activated is locked, so every past payroll still shows the exact rules it used. Only the super-admin can change them, with their password. <strong>Have your accountant confirm the figures before you activate a set.</strong></p>

    @if($errors->has('statutory'))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('statutory') }}</p>@endif

    @if($inForce)
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">In force this month: <strong>{{ $inForce->name }}</strong> (from {{ $inForce->effective_from->format('d M Y') }}). Payroll calculates pension, NHF and PAYE automatically.</div>
    @else
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">No rule set is active for this month, so payroll does <strong>not</strong> calculate PAYE, pension or NHF; HR must enter them by hand as deductions.</div>
    @endif

    <div class="space-y-4">
    @foreach($sets as $s)
        @php($r = $s->rules)
        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-black text-brand-700">{{ $s->name }}
                    <span class="ml-2 rounded px-2 py-0.5 text-xs {{ $s->status === StatutoryRuleSet::ACTIVE ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $s->status === StatutoryRuleSet::ACTIVE ? 'Active · locked' : 'Draft' }}</span></h2>
                <span class="text-sm text-gray-500">Effective {{ $s->effective_from->format('d M Y') }}@if($s->activated_at) · activated {{ $s->activated_at->format('d M Y') }}@endif</span>
            </div>
            @if($s->note)<p class="mt-1 text-sm text-gray-600">{{ $s->note }}</p>@endif

            @if($s->status === StatutoryRuleSet::DRAFT)
                @can('statutory.manage')
                    <form method="POST" action="{{ route('admin.statutory.update', $s) }}" class="mt-3">@csrf @method('PUT')
                        @include('admin._statutory-fields', ['r' => $r, 's' => $s])
                        <div class="mt-3 flex flex-wrap gap-2"><x-btn>Save draft</x-btn></div>
                    </form>
                    <div class="mt-3 flex flex-wrap items-end gap-3 border-t pt-3">
                        <form method="POST" action="{{ route('admin.statutory.activate', $s) }}" class="flex items-end gap-2" data-confirm="Activate this rule set? It will be locked and used for payroll from {{ $s->effective_from->format('F Y') }}.">@csrf
                            <x-field name="password" label="Your password" type="password" required autocomplete="current-password" /><x-btn>Activate</x-btn></form>
                        <form method="POST" action="{{ route('admin.statutory.destroy', $s) }}" data-confirm="Delete this draft?">@csrf @method('DELETE')<x-btn variant="ghost">Delete draft</x-btn></form>
                    </div>
                @else
                    @include('admin._statutory-summary', ['r' => $r])
                @endcan
            @else
                @include('admin._statutory-summary', ['r' => $r])
                @can('statutory.manage')
                    <form method="POST" action="{{ route('admin.statutory.copy', $s) }}" class="mt-3">@csrf<x-btn variant="ghost">Copy as a new draft</x-btn></form>
                @endcan
            @endif
        </x-card>
    @endforeach

    @can('statutory.manage')
        <x-card>
            <h2 class="mb-3 font-black text-brand-700">New rule set</h2>
            <form method="POST" action="{{ route('admin.statutory.store') }}">@csrf
                @include('admin._statutory-fields', ['r' => null, 's' => null])
                <div class="mt-3"><x-btn>Save as draft</x-btn></div>
            </form>
        </x-card>
    @endcan
    </div>
</x-layout>
