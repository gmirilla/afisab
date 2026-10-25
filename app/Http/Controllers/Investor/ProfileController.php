<?php

namespace App\Http\Controllers\Investor;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Models\InvestorProfile;
use App\Services\Profile\ProfilePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('investor.profile', ['profile' => $request->user()->investorProfile]);
    }

    public function update(Request $request): RedirectResponse
    {
        $profile = $request->user()->investorProfile;

        if ($request->input('section') === 'nin') {
            return $this->addNin($request, $profile);
        }

        // The agreement's schedule asks for these. They are not identity checks, so they can be updated at any time.
        $extras = $request->validate([
            'title' => ['nullable', 'string', 'in:Mr.,Mrs.,Miss,Ms.,Alh.,Alhaja,Chief,Dr.,Engr.,Prof.'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'occupation_address' => ['nullable', 'string', 'max:255'],
            'nominee_name' => ['nullable', 'string', 'max:150'],
            'nominee_relationship' => ['nullable', 'string', 'max:80'],
            'nominee_phone' => ['nullable', 'string', 'regex:/^[0-9+\s()-]{7,20}$/'],
        ]);

        if ($request->input('section') === 'agreement' || $profile->kyc_status === KycStatus::Approved) {
            $profile->update($extras);
            activity()->performedOn($profile)->causedBy($request->user())->log('Occupation and nominee updated');

            return back()->with('status', 'Your occupation and nominee details were saved. Your verified details can only be changed by support.');
        }

        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9+\s()-]{7,20}$/'],
            'date_of_birth' => ['required', 'date', 'before:-18 years'],
            'address' => ['required', 'string', 'max:255'],
            'nin' => ['required', 'digits:11', $this->uniqueNin($profile)],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'digits:10'],
            'account_name' => ['required', 'string', 'max:150'],
            'id_document' => [$profile->id_document_path ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        if ($request->hasFile('id_document')) {
            $data['id_document_path'] = $request->file('id_document')->store('kyc'); // private disk
        }
        unset($data['id_document']);

        $profile->update($data + $extras + ['kyc_status' => KycStatus::Submitted, 'kyc_note' => null]);
        activity()->performedOn($profile)->causedBy($request->user())->log('KYC submitted');

        return back()->with('status', 'Details submitted for verification.');
    }

    /** For an investor verified before the NIN was asked for: add it once; after that only support can change it. */
    private function addNin(Request $request, InvestorProfile $profile): RedirectResponse
    {
        if ($profile->nin) {
            return back()->withErrors(['nin' => 'Your NIN is already on record. Contact support to change it.']);
        }
        $data = $request->validate(['nin' => ['required', 'digits:11', $this->uniqueNin($profile)]]);

        $profile->update(['nin' => $data['nin']]);
        activity()->performedOn($profile)->causedBy($request->user())->log('NIN added');

        return back()->with('status', 'Your NIN was saved. Thank you.');
    }

    /** A NIN can belong to only one account. The message is deliberately vague so it cannot be used to look up other people. */
    private function uniqueNin(InvestorProfile $profile): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($profile) {
            if (InvestorProfile::where('nin_hash', InvestorProfile::hashNin((string) $value))->where('id', '!=', $profile->id)->exists()) {
                $fail('This NIN cannot be used. Please check it, or contact support.');
            }
        };
    }

    /** The investor's own picture (never public, never shared with other investors). */
    public function photo(Request $request): Response
    {
        $path = $request->user()->investorProfile?->photo_path;
        abort_unless($path && Storage::exists($path), 404);

        return response(Storage::get($path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store']);
    }

    public function updatePhoto(Request $request, ProfilePhotoService $photos): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=100,min_height=100,max_width=8000,max_height=8000'],
        ], ['photo.dimensions' => 'The picture must be at least 100x100 pixels and no larger than 8000x8000.', 'photo.required' => 'Please choose a picture.']);

        $profile = $request->user()->investorProfile;
        if ($photos->store($profile, $request->file('photo')) === null) {
            return back()->withErrors(['photo' => 'That picture could not be read. Please upload a JPG, PNG or WebP photo.']);
        }
        activity()->performedOn($profile)->causedBy($request->user())->log('Profile picture updated');

        return back()->with('status', 'Your profile picture was updated.');
    }

    public function destroyPhoto(Request $request, ProfilePhotoService $photos): RedirectResponse
    {
        $profile = $request->user()->investorProfile;
        if ($profile->photo_path) {
            $photos->remove($profile);
            activity()->performedOn($profile)->causedBy($request->user())->log('Profile picture removed');
        }

        return back()->with('status', 'Your profile picture was removed.');
    }
}
