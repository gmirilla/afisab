<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Notifications\EnquiryReceived;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;

/**
 * The forms on the public site. Open to anyone, so every defence is here: a rate limit (in the route), a hidden
 * field only a script would fill in, a minimum time to fill the form in, and strict validation. Nothing is emailed
 * to the person who wrote in (that would let anyone make us email a stranger); the shared mailbox is notified instead.
 */
class EnquiryController extends Controller
{
    private const MIN_SECONDS = 4;

    private const MAX_SECONDS = 7200;

    public function store(Request $request): RedirectResponse
    {
        $type = $request->input('type');
        abort_unless(in_array($type, array_keys(Enquiry::TYPES), true), 422);

        $this->guardAgainstBots($request);

        $data = $request->validate($this->rules($type), [
            'consent.accepted' => 'Please confirm that we may contact you about this enquiry.',
            'consent.required' => 'Please confirm that we may contact you about this enquiry.',
            'email.required' => 'Please give an email address so we can reply.',
            'phone.required' => 'Please give a phone number so we can reach you.',
        ]);

        $enquiry = Enquiry::create(collect($data)->except(['consent', 'started', 'website'])->all() + ['type' => $type, 'consented_at' => now(), 'status' => Enquiry::NEW]);
        activity()->performedOn($enquiry)->log('Website enquiry received');

        Notification::route('mail', config('afisab.contact.enquiry_email'))->notify(new EnquiryReceived($enquiry));

        $page = [Enquiry::QUOTE => 'public.buyers', Enquiry::SUPPLIER => 'public.suppliers'][$type] ?? 'public.contact';

        return redirect()->to(route($page).'#enquiry')->with('enquiry_sent', 'Thank you. Your message has reached AFISAB NIG. LTD and we will respond using the details you gave.');
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(string $type): array
    {
        $common = [
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'started' => ['required', 'string'],
            'website' => ['nullable', 'string', 'max:0'],
        ];

        return array_merge($common, match ($type) {
            Enquiry::QUOTE => [
                'email' => ['required', 'email:rfc', 'max:150'], 'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{7,32}$/'],
                'commodity' => ['nullable', 'string', 'max:100'], 'quantity' => ['nullable', 'string', 'max:100'], 'location' => ['nullable', 'string', 'max:150'],
            ],
            Enquiry::SUPPLIER => [
                'email' => ['nullable', 'email:rfc', 'max:150'], 'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{7,32}$/'],
                'commodity' => ['required', 'string', 'max:100'], 'quantity' => ['nullable', 'string', 'max:100'], 'location' => ['required', 'string', 'max:150'],
            ],
            default => [
                'email' => ['required', 'email:rfc', 'max:150'], 'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{7,32}$/'],
                'message' => ['required', 'string', 'max:2000'],
            ],
        });
    }

    private function guardAgainstBots(Request $request): void
    {
        // A person never sees the "website" field; a script that fills every input does.
        if (filled($request->input('website'))) {
            abort(422, 'Could not send your message.');
        }

        try {
            $started = (int) Crypt::decryptString((string) $request->input('started'));
        } catch (DecryptException) {
            abort(422, 'Please reload the page and try again.');
        }
        $elapsed = now()->timestamp - $started;
        if ($elapsed < self::MIN_SECONDS) {
            abort(422, 'That was sent too quickly. Please try again.');
        }
        if ($elapsed > self::MAX_SECONDS) {
            abort(422, 'This form has expired. Please reload the page and try again.');
        }
    }
}
