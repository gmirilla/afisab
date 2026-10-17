<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The inbox for messages from the public website. */
class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(Enquiry::TYPES))],
            'status' => ['nullable', Rule::in(array_keys(Enquiry::STATUSES))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $like = ! empty($f['q']) ? '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($f['q'])).'%' : null;

        return view('admin.enquiries.index', [
            'enquiries' => Enquiry::with('handler')
                ->when($f['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
                ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($like, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('company', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('commodity', 'like', $like)))
                ->orderByRaw("case status when 'new' then 0 when 'contacted' then 1 else 2 end")->orderByDesc('id')
                ->paginate(25)->withQueryString(),
            'filters' => $f + ['type' => null, 'status' => null, 'q' => null],
            'newCount' => Enquiry::where('status', Enquiry::NEW)->count(),
        ]);
    }

    public function show(Enquiry $enquiry): View
    {
        return view('admin.enquiries.show', ['enquiry' => $enquiry->load(['handler', 'supplier'])]);
    }

    public function update(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Enquiry::STATUSES))],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $enquiry->update($data + ['handled_by' => $request->user()->id, 'handled_at' => now()]);
        activity()->performedOn($enquiry)->causedBy($request->user())->withProperties(['status' => $data['status']])->log('Website enquiry updated');

        return back()->with('status', 'Enquiry updated.');
    }

    /** A supplier who wrote in becomes a supplier record Procurement can buy from. */
    public function makeSupplier(Request $request, Enquiry $enquiry): RedirectResponse
    {
        if ($enquiry->type !== Enquiry::SUPPLIER || $enquiry->supplier_id !== null) {
            return back()->withErrors(['enquiry' => 'This enquiry cannot be turned into a supplier (it is not supplier interest, or already was).']);
        }

        DB::transaction(function () use ($request, $enquiry) {
            $supplier = Supplier::create([
                'name' => $enquiry->company ?: $enquiry->name,
                'type' => 'supplier',
                'phone' => $enquiry->phone,
                'location' => $enquiry->location,
                'is_active' => true,
                'notes' => "From website enquiry #{$enquiry->id}. Contact: {$enquiry->name}. Offers: {$enquiry->commodity}".($enquiry->quantity ? " ({$enquiry->quantity})" : '').'.',
            ]);
            $enquiry->update(['supplier_id' => $supplier->id, 'status' => Enquiry::CONTACTED, 'handled_by' => $request->user()->id, 'handled_at' => now()]);
            activity()->performedOn($supplier)->causedBy($request->user())->withProperties(['enquiry' => $enquiry->id])->log('Supplier created from website enquiry');
        });

        return back()->with('status', 'Supplier record created. Complete their type and bank details on the Suppliers page.');
    }
}
