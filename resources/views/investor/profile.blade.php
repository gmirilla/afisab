@php use App\Enums\KycStatus; @endphp
<x-layout title="Profile & KYC">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Profile &amp; identity verification</h1>
    <p class="mb-6 text-sm text-gray-600">Status: <strong>{{ $profile->kyc_status->label() }}</strong>
        @if($profile->kyc_note) — {{ $profile->kyc_note }} @endif</p>
    @if($profile->kyc_status === KycStatus::Approved)
        <x-card>Your identity has been verified. Contact support to change verified details.</x-card>
    @else
        <x-card>
            <form method="POST" action="{{ route('investor.profile.update') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">@csrf @method('PUT')
                <x-field name="phone" label="Phone" :value="$profile->phone" required />
                <x-field name="date_of_birth" label="Date of birth" type="date" :value="$profile->date_of_birth?->format('Y-m-d')" required />
                <x-field name="address" label="Residential address" :value="$profile->address" required />
                <x-field name="bvn" label="BVN (11 digits)" inputmode="numeric" maxlength="11" required />
                <x-field name="bank_name" label="Bank name" :value="$profile->bank_name" required />
                <x-field name="account_number" label="Account number (10 digits)" :value="$profile->account_number" inputmode="numeric" maxlength="10" required />
                <x-field name="account_name" label="Account name" :value="$profile->account_name" required />
                <x-field name="id_document" label="Government ID (JPG, PNG, PDF, max 4MB)" type="file" />
                <div class="md:col-span-2"><x-btn>Submit for verification</x-btn></div>
            </form>
        </x-card>
    @endif

    <x-card class="mt-6">
        <h2 class="font-black text-brand-700">Details for your investment agreement</h2>
        <p class="mb-4 text-sm text-gray-600">Your receipt and agreement use your title, and the agreement lists your occupation and a nominee. These are optional and you can change them at any time.</p>
        <form method="POST" action="{{ route('investor.profile.update') }}" class="grid gap-4 md:grid-cols-2">@csrf @method('PUT')
            <input type="hidden" name="section" value="agreement">
            <label class="block text-sm font-bold text-gray-700">Title
                <select name="title" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 font-normal">
                    <option value="">None</option>
                    @foreach(['Mr.','Mrs.','Miss','Ms.','Alh.','Alhaja','Chief','Dr.','Engr.','Prof.'] as $t)
                        <option value="{{ $t }}" @selected(old('title', $profile->title) === $t)>{{ $t }}</option>
                    @endforeach
                </select></label>
            <x-field name="occupation" label="Occupation" :value="$profile->occupation" />
            <x-field name="occupation_address" label="Business or work address" :value="$profile->occupation_address" />
            <x-field name="nominee_name" label="Nominee (who should be contacted on your behalf)" :value="$profile->nominee_name" />
            <x-field name="nominee_relationship" label="Nominee's relationship to you" :value="$profile->nominee_relationship" />
            <x-field name="nominee_phone" label="Nominee's phone" :value="$profile->nominee_phone" />
            <div class="flex items-end"><x-btn>Save</x-btn></div>
        </form>
    </x-card>
</x-layout>
