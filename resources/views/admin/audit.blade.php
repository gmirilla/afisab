@php use Illuminate\Support\Str; @endphp
<x-layout title="Audit trail">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Audit trail</h1>
    <p class="mb-4 text-sm text-gray-600">A permanent record of who did what. Entries cannot be edited from the application.</p>

    <x-card class="mb-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-field name="q" label="Action contains" :value="$filters['q'] ?? null" />
            <x-field name="who" label="Person" :value="$filters['who'] ?? null" />
            <label class="block text-sm font-bold text-gray-700">Record type
                <select name="type" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal"><option value="">All</option>
                    @foreach($types as $t)<option value="{{ $t }}" @selected(($filters['type'] ?? '') === $t)>{{ $t }}</option>@endforeach</select></label>
            <x-field name="from" label="From" type="date" :value="$filters['from'] ?? null" />
            <x-field name="to" label="To" type="date" :value="$filters['to'] ?? null" />
            <x-btn>Filter</x-btn>
        </form>
    </x-card>

    <x-card>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">When</th><th>Who</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>
            @forelse($entries as $a)
                <tr class="border-b align-top last:border-0">
                    <td class="py-2 whitespace-nowrap">{{ $a->created_at->format('d M Y H:i') }}</td>
                    <td>{{ $a->causer?->name ?? 'System' }}</td>
                    <td>{{ $a->description }}</td>
                    <td class="text-xs">{{ $a->subject_type }} #{{ $a->subject_id }}@if($a->subject && isset($a->subject->reference)) <br><span class="font-mono">{{ $a->subject->reference }}</span>@endif</td>
                    <td class="font-mono text-xs text-gray-600">{{ Str::limit($a->properties->toJson(), 120) }}</td>
                </tr>
            @empty <tr><td colspan="5" class="py-6 text-center text-gray-500">No entries match.</td></tr> @endforelse
        </tbody></table>
        <div class="mt-4">{{ $entries->links() }}</div>
    </x-card>
</x-layout>
