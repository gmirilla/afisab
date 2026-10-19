<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The company's address and extra contact lines as printed on receipts. A single row that only the super-admin edits.
 * Email and first phone number come from configuration (they are on the public website too); nothing here is invented:
 * an empty field is simply not printed.
 */
#[Fillable(['address', 'phone_secondary', 'investment_email', 'website', 'updated_by'])]
class CompanyDetail extends Model
{
    public static function current(): self
    {
        return self::first() ?? new self;
    }

    /** @return list<string> address lines, trimmed, blank lines dropped */
    public function addressLines(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->address) ?: [])));
    }

    /** @return list<string> every phone number to print, the configured one first */
    public function phones(): array
    {
        $first = (string) config('afisab.contact.phone');

        return array_values(array_filter([self::pretty($first), $this->phone_secondary ? self::pretty($this->phone_secondary) : null]));
    }

    /** @return list<string> */
    public function emails(): array
    {
        return array_values(array_unique(array_filter([(string) config('afisab.contact.email'), $this->investment_email])));
    }

    /** +2348057520669 -> +234 805 752 0669 (other formats are left as typed) */
    public static function pretty(string $phone): string
    {
        $clean = preg_replace('/[\s()-]/', '', $phone);

        return preg_match('/^\+234(\d{3})(\d{3})(\d{4})$/', $clean, $m) ? "+234 {$m[1]} {$m[2]} {$m[3]}" : $phone;
    }
}
