<?php

namespace App\Http\Controllers\Investor;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        // The agreement's schedule asks for these. They are not identity checks, so they can be updated at any time.
        $extras = $request->validate([
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
            'bvn' => ['required', 'digits:11'],
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
}
