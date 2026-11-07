<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Notifications\EnquiryAcknowledged;
use App\Notifications\EnquiryReceived;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The forms on the public site. Open to anyone, so every defence is here: a rate limit (in the route), a hidden
 * field only a script would fill in, a minimum time to fill the form in, and strict validation. Nothing is emailed
 * to the person who wrote in except a short fixed acknowledgement, and only once an hour per address: it contains nothing the
 * visitor typed, so the form cannot be used to send a stranger a message of the visitor's choosing. The shared mailbox is notified too.
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

        if (in_array($type, [Enquiry::QUOTE, Enquiry::SUPPLIER], true)) {
            $request->merge(['items' => $this->items($request)]);
        }

        $data = $request->validate($this->rules($type), [
            'consent.accepted' => 'Please confirm that we may contact you about this enquiry.',
            'consent.required' => 'Please confirm that we may contact you about this enquiry.',
            'email.required' => 'Please give an email address so we can reply.',
            'phone.required' => 'Please give a phone number so we can reach you.',
            'items.required' => 'Please choose at least one commodity.',
            'items.min' => 'Please choose at least one commodity.',
            'items.max' => 'You can list up to '.Enquiry::MAX_ITEMS.' commodities. Put the rest in your message.',
            'items.*.commodity.required' => 'Please choose a commodity for each row (for "Other", type its name).',
            'items.*.commodity.max' => 'A commodity name is too long.',
            'items.*.quantity.max' => 'A quantity is too long.',
        ]);

        $enquiry = DB::transaction(function () use ($data, $type) {
            $items = $data['items'] ?? [];
            // The first item is also kept in the single commodity/quantity columns, as the summary older records use.
            $enquiry = Enquiry::create(collect($data)->except(['consent', 'started', 'website', 'items'])->all()
                + ['commodity' => $items[0]['commodity'] ?? null, 'quantity' => $items[0]['quantity'] ?? null, 'type' => $type, 'consented_at' => now(), 'status' => Enquiry::NEW]);
            foreach (array_values($items) as $i => $item) {
                $enquiry->items()->create(['commodity' => $item['commodity'], 'quantity' => $item['quantity'] ?? null, 'position' => $i + 1]);
            }

            return $enquiry;
        });
        activity()->performedOn($enquiry)->log('Website enquiry received');

        Notification::route('mail', config('afisab.contact.enquiry_email'))->notify(new EnquiryReceived($enquiry));

        if ($enquiry->email && Cache::add('enquiry-ack:'.sha1(strtolower($enquiry->email)), 1, now()->addHour())) {
            Notification::route('mail', $enquiry->email)->notify(new EnquiryAcknowledged);
        }

        $page = [Enquiry::QUOTE => 'public.buyers', Enquiry::SUPPLIER => 'public.suppliers'][$type] ?? 'public.contact';

        return redirect()->to(route($page).'#enquiry')->with('enquiry_sent', 'Thank you. Your message has reached AFISAB NIG. LTD and we will respond using the details you gave.');
    }

    /**
     * The commodity rows from the form, cleaned up: blank rows dropped, "Other" replaced by the name typed, repeats
     * removed. (A single `commodity`/`quantity` pair, as older forms and links send it, is treated as one row.)
     *
     * @return list<array{commodity: string, quantity: ?string}>
     */
    private function items(Request $request): array
    {
        $rows = $request->input('items');
        if (! is_array($rows)) {
            $rows = [['commodity' => $request->input('commodity'), 'quantity' => $request->input('quantity')]];
        }

        $items = [];
        $seen = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $commodity = trim((string) ($row['commodity'] ?? ''));
            $quantity = trim((string) ($row['quantity'] ?? ''));
            if ($commodity === 'Other') {
                $commodity = trim((string) ($row['other'] ?? '')); // empty if they did not say what: refused with a clear message
            }
            if ($commodity === '' && $quantity === '' && trim((string) ($row['other'] ?? '')) === '') {
                continue; // an untouched spare row
            }
            $key = mb_strtolower($commodity);
            if ($commodity !== '' && isset($seen[$key])) {
                continue; // the same commodity twice: keep the first
            }
            $seen[$key] = true;
            $items[] = ['commodity' => $commodity, 'quantity' => $quantity === '' ? null : $quantity];
        }

        return $items;
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
                'location' => ['nullable', 'string', 'max:150'], 'items' => ['required', 'array', 'min:1', 'max:'.Enquiry::MAX_ITEMS], 'items.*.commodity' => ['required', 'string', 'max:100'], 'items.*.quantity' => ['nullable', 'string', 'max:100'],
            ],
            Enquiry::SUPPLIER => [
                'email' => ['nullable', 'email:rfc', 'max:150'], 'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{7,32}$/'],
                'location' => ['required', 'string', 'max:150'], 'items' => ['required', 'array', 'min:1', 'max:'.Enquiry::MAX_ITEMS], 'items.*.commodity' => ['required', 'string', 'max:100'], 'items.*.quantity' => ['nullable', 'string', 'max:100'],
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
