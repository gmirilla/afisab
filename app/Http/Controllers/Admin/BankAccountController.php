<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Where investors are told to send money. High-risk (a swapped account number diverts funds), so only the
 * super-admin can change it, the current password is required, and every change is audited with old and new values.
 */
class BankAccountController extends Controller
{
    private const TRACKED = ['bank_name', 'account_number', 'account_name', 'note', 'is_active'];

    public function index(): View
    {
        return view('admin.bank-accounts', [
            'accounts' => BankAccount::orderByDesc('is_active')->orderBy('id')->get(),
            'usingEnv' => ! BankAccount::exists(),
            'env' => (array) config('afisab.bank'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new BankAccount);
    }

    public function update(Request $request, BankAccount $account): RedirectResponse
    {
        return $this->save($request, $account);
    }

    private function save(Request $request, BankAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'digits:10', 'not_in:0000000000'],
            'account_name' => ['required', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'current_password'],
        ], ['password.current_password' => 'Your password is incorrect.', 'account_number.digits' => 'The account number must be exactly 10 digits.']);

        $active = $account->exists ? $request->boolean('is_active') : true;

        return DB::transaction(function () use ($request, $account, $data, $active) {
            if ($account->exists && ! $active && $account->is_active
                && ! BankAccount::where('is_active', true)->whereKeyNot($account->id)->lockForUpdate()->exists()) {
                return back()->withInput()->withErrors(['bank' => 'At least one account must stay active, otherwise investors have nowhere to send transfers. Add the new account first.']);
            }

            $before = $account->exists ? $account->only(self::TRACKED) : null;
            $account->fill([
                'bank_name' => $data['bank_name'], 'account_number' => $data['account_number'], 'account_name' => $data['account_name'],
                'note' => $data['note'] ?? null, 'is_active' => $active, 'updated_by' => $request->user()->id,
            ])->save();

            activity()->performedOn($account)->causedBy($request->user())
                ->withProperties(['before' => $before, 'after' => $account->only(self::TRACKED)])
                ->log($before ? 'Bank account changed' : 'Bank account added');

            return back()->with('status', $before ? 'Bank account updated.' : 'Bank account added. Investors now see it on their payment page.');
        });
    }
}
