<?php

namespace App\Http\Controllers\Admin\Trading;

use App\Http\Controllers\Concerns\AttemptsActions;
use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\InventoryLot;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Suppliers, buyers and the stock list. */
class PartyController extends Controller
{
    use AttemptsActions;

    public function suppliers(): View
    {
        return view('admin.trading.suppliers', ['suppliers' => Supplier::orderBy('name')->get()]);
    }

    public function saveSupplier(Request $request, ?Supplier $supplier = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['farmer', 'aggregator', 'supplier'])],
            'phone' => ['nullable', 'string', 'max:32'],
            'location' => ['nullable', 'string', 'max:150'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'digits:10'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['is_active'] = $request->boolean('is_active', ! $supplier);

        $saved = $supplier ? tap($supplier)->update($data) : Supplier::create($data);
        activity()->performedOn($saved)->causedBy($request->user())->log('Supplier saved');

        return back()->with('status', 'Supplier saved.');
    }

    public function buyers(): View
    {
        return view('admin.trading.buyers', ['buyers' => Buyer::orderBy('name')->get()]);
    }

    public function saveBuyer(Request $request, ?Buyer $buyer = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['local', 'processor', 'export'])],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $data['is_active'] = $request->boolean('is_active', ! $buyer);

        $saved = $buyer ? tap($buyer)->update($data) : Buyer::create($data);
        activity()->performedOn($saved)->causedBy($request->user())->log('Buyer saved');

        return back()->with('status', 'Buyer saved.');
    }

    public function inventory(): View
    {
        return view('admin.trading.inventory', [
            'lots' => InventoryLot::with(['commodity', 'cycle', 'purchase.supplier'])->orderByDesc('id')->get(),
        ]);
    }
}
