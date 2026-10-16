<?php

namespace App\Services\Payments;

use App\Models\BankAccount;
use Illuminate\Support\Collection;

/**
 * The company bank accounts shown to investors for transfers.
 *
 * Managed by the super-admin on /admin/bank-accounts. Until the first account is saved there, the
 * AFISAB_BANK_* environment settings (config afisab.bank) are used, so existing installs keep working.
 */
class BankAccounts
{
    /** @return Collection<int, array{name: string, account_number: string, account_name: string, note: ?string}> */
    public function active(): Collection
    {
        if (BankAccount::exists()) {
            return BankAccount::where('is_active', true)->orderBy('id')->get()->map(fn (BankAccount $a) => [
                'name' => $a->bank_name, 'account_number' => $a->account_number, 'account_name' => $a->account_name, 'note' => $a->note,
            ]);
        }

        $env = (array) config('afisab.bank');

        return collect([[
            'name' => (string) ($env['name'] ?? ''), 'account_number' => (string) ($env['account_number'] ?? ''),
            'account_name' => (string) ($env['account_name'] ?? ''), 'note' => null,
        ]]);
    }

    /** A usable account: named bank, 10-digit number that is not the placeholder, named holder. */
    public static function isReal(array $bank): bool
    {
        return trim((string) $bank['name']) !== '' && ! str_starts_with((string) $bank['name'], 'Set ')
            && preg_match('/^\d{10}$/', (string) $bank['account_number']) === 1 && $bank['account_number'] !== '0000000000'
            && trim((string) $bank['account_name']) !== '';
    }
}
