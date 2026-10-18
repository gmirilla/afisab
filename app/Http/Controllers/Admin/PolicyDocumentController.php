<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\PolicyDocument;
use App\Services\Policy\PolicyDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Staff view of an investor's agreements: reprint (download), resend by email, and re-issue a corrected version. */
class PolicyDocumentController extends Controller
{
    public function download(Request $request, PolicyDocument $document, PolicyDocumentService $documents): StreamedResponse
    {
        activity()->performedOn($document->investment)->causedBy($request->user())->withProperties(['version' => $document->version])->log('Policy document downloaded by staff');

        return $documents->download($document);
    }

    public function send(Request $request, Investment $investment, PolicyDocumentService $documents): RedirectResponse
    {
        try {
            $documents->send($investment, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['policy' => $e->getMessage()]);
        }

        return back()->with('status', 'The agreement has been emailed to '.$investment->user->email.'.');
    }

    /** First issue (when the automatic one did not happen) or a corrected version that supersedes the current one. */
    public function reissue(Request $request, Investment $investment, PolicyDocumentService $documents): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:200'], 'send_copy' => ['nullable', 'boolean']]);

        try {
            $first = $investment->currentPolicyDocument() === null;
            $document = $documents->issue($investment, $request->user(), $data['reason'], force: true);
            if ($request->boolean('send_copy')) {
                $investment->user->notify(new \App\Notifications\PolicyDocumentDelivered($document, $first ? 'issued' : 'reissued'));
            }
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['policy' => $e->getMessage()]);
        }

        return back()->with('status', "Version {$document->version} issued".($request->boolean('send_copy') ? ' and emailed to the investor.' : '.'));
    }
}
