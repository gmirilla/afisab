<x-layout title="Policy signatory, stamp and address">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Policy signatory, company stamp and address</h1>
    <p class="mb-4 text-sm text-gray-600">Whose signature appears for the company on every new investment agreement. A signature is a commitment of the company, so only the super-admin can change it, your password is required, and every change is recorded. The image is kept private and is only placed inside the agreement PDFs.
        <strong>Replacing it never changes an agreement already issued</strong>; each one remembers who signed it.</p>
    @if($errors->any() && ! $errors->hasAny(['password', 'signature', 'name', 'title', 'stamp', 'address', 'phone_secondary', 'investment_email', 'website']))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</p>@endif

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

    <x-card class="mb-6">
        <h2 class="mb-1 font-black text-brand-700">Company address and contact lines</h2>
        <p class="mb-3 text-sm text-gray-600">Printed in the header of every new investment receipt. Nothing is printed for a field you leave empty, so enter only what is confirmed. The first phone number and the email are taken from the website settings. Receipts already issued are unchanged.</p>
        <form method="POST" action="{{ route('admin.signatory.details') }}" class="grid gap-3 md:grid-cols-2">@csrf @method('PUT')
            <label class="block text-sm font-bold text-gray-700 md:col-span-2">Office address (one line per row)
                <textarea name="address" rows="3" maxlength="500" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 font-normal focus:border-brand-600 focus:outline-none">{{ old('address', $details->address) }}</textarea></label>
            <x-field name="phone_secondary" label="Second phone number (optional)" :value="$details->phone_secondary" />
            <x-field name="investment_email" label="Investment email (optional)" type="email" :value="$details->investment_email" />
            <x-field name="website" label="Website (optional)" :value="$details->website" />
            <x-field name="password" label="Your password" type="password" required autocomplete="current-password" />
            <div class="flex items-end"><x-btn>Save company details</x-btn></div>
        </form>
    </x-card>

    @php($activeStamp = $stamps->firstWhere('is_active', true))
    <x-card class="mb-6">
        <h2 class="mb-1 font-black text-brand-700">Company stamp</h2>
        <p class="mb-3 text-sm text-gray-600">The round company stamp placed beside the signature on every new receipt. Kept private like the signature; replacing it never changes a receipt already issued.</p>
        @if($activeStamp)
            <div class="mb-4 flex flex-wrap items-center gap-6">
                <div class="rounded-lg border border-stone-200 bg-white p-3"><img src="{{ route('admin.signatory.stamp.image', $activeStamp) }}" alt="Company stamp" class="h-24 w-24 object-contain"></div>
                <p class="text-xs text-gray-500">Set {{ $activeStamp->created_at->format('d M Y H:i') }} by {{ $activeStamp->uploader?->name ?? 'unknown' }} · on {{ $receiptsWithStamp[$activeStamp->id] ?? 0 }} issued receipts</p>
            </div>
        @else
            <p class="mb-4 text-sm text-amber-700">No stamp is set. Receipts are being issued without the company stamp.</p>
        @endif
        <form method="POST" action="{{ route('admin.signatory.stamp.store') }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-2">@csrf
            <div class="md:col-span-2"><x-field name="stamp" label="{{ $activeStamp ? 'Replace the stamp' : 'Upload the stamp' }} (PNG or JPG, max 2 MB; a PNG with a transparent background looks best)" type="file" accept="image/png,image/jpeg" required /></div>
            <x-field name="password" label="Your password" type="password" required autocomplete="current-password" />
            <div class="flex items-end"><x-btn>Save stamp</x-btn></div>
        </form>
        @if($stamps->count() > 1)
            <table class="mt-5 w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Earlier stamps</th><th>Set</th><th>Receipts</th><th></th></tr></thead><tbody>
            @foreach($stamps->where('is_active', false) as $s)
                <tr class="border-b last:border-0">
                    <td class="py-2"><img src="{{ route('admin.signatory.stamp.image', $s) }}" alt="" class="h-12 w-12 object-contain"></td>
                    <td class="text-gray-600">{{ $s->created_at->format('d M Y') }}</td>
                    <td>{{ $receiptsWithStamp[$s->id] ?? 0 }}</td>
                    <td><form method="POST" action="{{ route('admin.signatory.stamp.activate', $s) }}" class="flex items-center gap-1">@csrf
                        <input type="password" name="password" placeholder="Your password" required class="w-36 rounded border px-2 py-1 text-xs"><x-btn variant="ghost">Use again</x-btn></form></td>
                </tr>
            @endforeach
            </tbody></table>
        @endif
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
