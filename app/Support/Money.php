<?php

namespace App\Support;

use InvalidArgumentException;

/** Integer-kobo money helpers. Never use floats for amounts. */
class Money
{
    /** Parse a user-entered naira string ("500,000" / "500000.50") into kobo. */
    public static function fromNaira(string $input): int
    {
        $clean = str_replace([',', ' ', '₦'], '', trim($input));

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $clean)) {
            throw new InvalidArgumentException('Invalid amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $clean), 2, '');

        return ((int) $whole) * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function format(int $kobo, string $symbol = '₦'): string
    {
        return $symbol.number_format($kobo / 100, 2);
    }
}
