<x-layout title="Policy signatory">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Policy signatory</h1>
    <p class="mb-4 text-sm text-gray-600">Whose signature appears for the company on every new investment agreement. A signature is a commitment of the company, so only the super-admin can change it, your password is required, and every change is recorded. The image is kept private and is only placed inside the agreement PDFs.
        <strong>Replacing it never changes an agreement already issued</strong>; each one remembers who signed it.</p>
    @if($errors->any() && ! $errors->has('password') && ! $errors->has('signature') && ! $errors->has('name') && ! $errors->has('title'))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</p>@endif

    @php($active = $signatories->firstWhere('is_active', true))
    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Current signatory</h2>
        @if($active)
            <div class="flex flex-wrap items-center gap-6">
                <div class="rounded-lg border border-stone-200 bg-white p-3"><img src="{{ route('admin.signatory.image', $active) }}" alt="Signature of {{ $active->name }}" class="h-20 max-w-xs object-contain"></div>
                <div><p class="font-bold">{{ $active->name }}</p><p class="text-sm text-gray-600">{{ $active->title }}</p>
                    <p class="text-xs text-gray-500">Set {{ $active->created_at->format('d M Y H:i') }} by {{ $active->uploader?->name ?? 'unknown' }} · on {{ $issuedWith[$active->id] ?? 0 }} issued agreements</p></div>
            </div>
        @else
            <p class="text-sm text-amber-700">No signatory is set. Agreements are being issued without the company signature.</p>
        @endif
    </x-card>

    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">{{ $active ? 'Replace the signatory' : 'Set the signatory' }}</h2>
        <form method="POST" action="{{ route('admin.signatory.store') }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-2">@csrf
            <x-field name="name" label="Full name" :value="old('name', $active?->name)" required />
            <x-field name="title" label="Title" :value="old('title', $active?->title ?? 'Managing Director / CEO')" required />
            <div class="md:col-span-2"><x-field name="signature" label="Signature image (PNG or JPG, max 1 MB; a PNG with a transparent background looks best)" type="file" accept="image/png,image/jpeg" required />
                <p class="mt-1 text-xs text-gray-500">Sign on white paper, photograph or scan it flat, and crop close. It is resized to fit the page.</p></div>
            <x-field name="password" label="Your password" type="password" required autocomplete="current-password" />
            <div class="flex items-end"><x-btn>Save signatory</x-btn></div>
        </form>
    </x-card>

    @if($signatories->count() > 1)
        <x-card>
            <h2 class="mb-3 font-black text-brand-700">Earlier signatories</h2>
            <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Signature</th><th>Name</th><th>Set</th><th>Agreements signed</th><th></th></tr></thead><tbody>
            @foreach($signatories->where('is_active', false) as $s)
                <tr class="border-b last:border-0">
                    <td class="py-2"><img src="{{ route('admin.signatory.image', $s) }}" alt="" class="h-10 max-w-[140px] object-contain"></td>
                    <td>{{ $s->name }}<br><span class="text-xs text-gray-500">{{ $s->title }}</span></td>
                    <td class="text-gray-600">{{ $s->created_at->format('d M Y') }}</td>
                    <td>{{ $issuedWith[$s->id] ?? 0 }}</td>
                    <td><form method="POST" action="{{ route('admin.signatory.activate', $s) }}" class="flex items-center gap-1">@csrf
                        <input type="password" name="password" placeholder="Your password" required class="w-36 rounded border px-2 py-1 text-xs"><x-btn variant="ghost">Use again</x-btn></form></td>
                </tr>
            @endforeach
            </tbody></table>
        </x-card>
    @endif
</x-layout>
