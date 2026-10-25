<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Models\InvestorProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycController extends Controller
{
    public function index(): View
    {
        return view('admin.kyc', [
            'profiles' => InvestorProfile::with('user')->where('kyc_status', KycStatus::Submitted)->oldest()->get(),
        ]);
    }

    public function document(InvestorProfile $profile): StreamedResponse
    {
        abort_unless($profile->id_document_path && Storage::exists($profile->id_document_path), 404);

        return Storage::response($profile->id_document_path);
    }

    public function photo(InvestorProfile $profile): Response
    {
        abort_unless($profile->photo_path && Storage::exists($profile->photo_path), 404);

        return response(Storage::get($profile->photo_path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store']);
    }

    public function review(Request $request, InvestorProfile $profile): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['required_if:decision,reject', 'nullable', 'string', 'max:500'],
        ]);

        abort_unless($profile->kyc_status === KycStatus::Submitted, 422, 'This submission is not awaiting review.');

        $approved = $data['decision'] === 'approve';
        $profile->update([
            'kyc_status' => $approved ? KycStatus::Approved : KycStatus::Rejected,
            'kyc_note' => $data['note'] ?? null,
            'kyc_reviewed_by' => $request->user()->id,
            'kyc_reviewed_at' => now(),
        ]);
        activity()->performedOn($profile)->causedBy($request->user())->log('KYC '.($approved ? 'approved' : 'rejected'));

        return back()->with('status', 'KYC '.($approved ? 'approved' : 'rejected').'.');
    }
}
