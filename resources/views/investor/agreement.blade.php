@php use App\Services\Policy\PolicyDocumentService as Doc; @endphp
<x-layout title="Investment Agreement">
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Please read this carefully.</strong> This is the agreement you accept when you subscribe to the {{ $plan->name }}.
            @if($c['frequency'])You chose to be paid <strong>{{ $c['frequency']->label() }}</strong>.@else Your choice of profit payment (clause 6.4) is made on the subscription form.@endif
            Blank dates and numbers are filled in when your payment is confirmed, and your signed copy is then emailed to you.
        </div>

        <article class="space-y-5 rounded-xl border border-stone-200 bg-white p-8 leading-relaxed text-gray-800 shadow-sm">
            <header class="text-center">
                <p class="font-display text-2xl font-bold text-brand-700">MUDHARABAH INVESTMENT AGREEMENT</p>
                <p class="mt-1 text-sm text-gray-500">Between {{ $c['company'] }} (the “Mudarib” or “Fund Manager”) and {{ $c['investor_name'] }} (the “Rabb-ul-Maal” or “Investor”)</p>
                <p class="mt-1 text-xs text-gray-400">Wording version {{ $c['template_version'] }}</p>
            </header>

            @foreach($sections as $section)
                <section>
                    <h2 class="mt-4 font-display text-lg font-bold text-brand-700">{{ $section['no'] }}. {{ $section['title'] }}</h2>
                    @foreach($section['blocks'] as $block)
                        @if(isset($block['p']))
                            <p class="mt-2 text-sm">{{ Doc::inline($block['p']) }}</p>
                        @elseif(isset($block['ul']))
                            <ul class="mt-2 list-disc space-y-1 pl-6 text-sm">@foreach($block['ul'] as $item)<li>{{ Doc::inline($item) }}</li>@endforeach</ul>
                        @elseif(isset($block['checks']))
                            <ul class="mt-2 space-y-1 pl-2 text-sm">@foreach($block['checks'] as [$label, $on])<li>{{ $on ? '☒' : '☐' }} {{ $label }}</li>@endforeach</ul>
                        @elseif(isset($block['schedule']))
                            <table class="mt-3 w-full border-collapse text-sm"><caption class="mb-1 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Schedule</caption><tbody>
                                @foreach($schedule as [$label, $lines])
                                    <tr class="border"><th class="w-2/5 border bg-stone-50 px-3 py-2 text-left align-top text-xs font-bold uppercase">{{ $label }}</th>
                                        <td class="border px-3 py-2">@foreach($lines as $line)<div>{{ $line }}</div>@endforeach</td></tr>
                                @endforeach
                            </tbody></table>
                        @elseif(isset($block['execution']))
                            <p class="mt-2 text-sm">By ticking the agreement box when you subscribe you accept this Agreement electronically. The time of your acceptance is recorded. {{ $c['company'] }} then signs, and your signed copy is issued and emailed to you.</p>
                        @endif
                    @endforeach
                </section>
            @endforeach
        </article>
        <p class="mt-6 text-center"><a class="text-sm font-bold text-brand-700 underline" href="{{ route('investor.plans') }}">← Back to the plans</a></p>
    </div>
</x-layout>
