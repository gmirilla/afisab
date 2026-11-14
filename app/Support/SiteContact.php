<?php

namespace App\Support;

use App\Models\CompanyDetail;

/**
 * The contact details the public site shows. Email and the first phone number come from configuration; a second phone and
 * the office address appear only once the super-admin has entered them (Signatory, stamp and address page). Nothing is invented.
 */
class SiteContact
{
    /** @return array{company: string, short: string, email: string, phone: string, phones: list<string>, phoneShown: string, whatsapp: string, location: string, home: string} */
    public static function get(): array
    {
        $details = CompanyDetail::current();
        $phone = (string) config('afisab.contact.phone');
        $phones = $details->phones();

        return [
            'company' => (string) config('afisab.company_name'),
            'short' => 'AFISAB',
            'email' => (string) config('afisab.contact.email'),
            'phone' => $phone,
            'phones' => $phones,
            'phoneShown' => $phones[0] ?? $phone,
            'whatsapp' => ltrim((string) preg_replace('/[^+0-9]/', '', $phone), '+'),
            'location' => $details->addressLines()[0] ?? 'Nigeria',
            'home' => route('home'),
        ];
    }
}
