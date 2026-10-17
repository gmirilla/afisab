@php
    use App\Support\Money;
    use App\Models\Purchase; use App\Models\CycleExpense; use App\Models\Sale; use App\Models\TradeCycle;
    $open = $cycle->isOpen();
    $pending = $cycle->isPending();
    $live = $open || $pending; // capital can still be changed
    $paidPurchases = $cycle->purchases->whereIn('status', [Purchase::PAID, Purchase::RECEIVED])->sum('total');
    $paidExpenses = $cycle->expenses->where('status', CycleExpense::PAID)->sum('amount');
    $paidSales = $cycle->sales->where('status', Sale::PAID)->sum('total');
@endphp
<x-layout :title="$cycle->reference">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-black text-brand-700">{{ $cycle->name }}
            <span class="font-mono text-base font-normal text-gray-500">{{ $cycle->reference }}</span>
            <span class="ml-2 rounded bg-brand-50 px-2 py-1 text-sm">{{ ucfirst($cycle->status) }}</span></h1>
        <a class="text-sm underline" href="{{ route('admin.trading.cycles') }}">← All cycles</a>
    </div>
    <p class="mb-4 text-sm text-gray-600">Commodity: <strong>{{ $cycle->commodity->name }}</strong> @if($cycle->notes) · {{ $cycle->notes }} @endif
        · proposed by {{ $cycle->proposer?->name }} {{ $cycle->opened_at?->format('d M Y') }}@if($cycle->approver && $cycle->status !== TradeCycle::CANCELLED) · approved by {{ $cycle->approver->name }} {{ $cycle->approved_at?->format('d M Y') }}@endif</p>

    @if($errors->has('action'))<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('action') }}</p>@endif
    @if($cashWarning)<p class="mb-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"><strong>Cash warning.</strong> {{ $cashWarning }}</p>@endif
    @if($cycle->status === TradeCycle::CANCELLED && $cycle->decision_note)
        <p class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800">Rejected by {{ $cycle->approver?->name }}: {{ $cycle->decision_note }}</p>
    @endif
    @if($pending)
        <x-card class="mb-6 border-amber-300 bg-amber-50">
            <h2 class="font-black text-amber-900">Waiting for a Director's approval</h2>
            <p class="mb-3 text-sm text-amber-900">Capital of {{ Money::format($budget) }} is reserved for this cycle, but no purchase, expense or sale can be recorded until it is approved. Approval is by a Director who did not propose it.</p>
            @can('cycles.approve')
                <div class="flex flex-wrap items-end gap-3">
                    <form method="POST" action="{{ route('admin.trading.cycles.approve', $cycle) }}" data-confirm="Approve this cycle with a budget of {{ Money::format($budget) }}?">@csrf<x-btn>Approve cycle</x-btn></form>
                    <form method="POST" action="{{ route('admin.trading.cycles.reject', $cycle) }}" class="flex items-end gap-2">@csrf
                        <input name="note" placeholder="Reason for rejecting" required class="w-56 rounded border px-3 py-2 text-sm"><x-btn variant="danger">Reject</x-btn></form>
                </div>
            @endcan
        </x-card>
    @endif

    <div class="mb-6 grid gap-4 md:grid-cols-5">
        <x-card><p class="text-xs text-gray-500">Budget (allocated)</p><p class="font-black">{{ Money::format($budget) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">Committed to purchases</p><p class="font-black">{{ Money::format($committed) }}</p><p class="text-xs text-gray-500">Left: {{ Money::format($budget - $committed) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">Purchases paid</p><p class="font-black">{{ Money::format($paidPurchases) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">Expenses paid</p><p class="font-black">{{ Money::format($paidExpenses) }}</p></x-card>
        <x-card><p class="text-xs text-gray-500">Sales received</p><p class="font-black">{{ Money::format($paidSales) }}</p></x-card>
    </div>
    @if($cycle->status === TradeCycle::CLOSED)
        <x-card class="mb-6 border-green-300 bg-green-50">
            Closed {{ $cycle->closed_at->format('d M Y') }}. Net profit <strong>{{ Money::format($cycle->net_profit) }}</strong>,
            of which {{ Money::format($cycle->retained) }} was retained (20%). This result is included in the next monthly profit distribution.
        </x-card>
    @endif

    {{-- Allocations --}}
    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Investor capital allocated</h2>
        <p class="mb-3 text-xs text-gray-500">Profit from this cycle is shared across these policies in proportion to the amounts below.</p>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Policy</th><th>Investor</th><th class="text-right">Allocated</th><th></th></tr></thead><tbody>
            @forelse($cycle->allocations as $a)
                <tr class="border-b last:border-0"><td class="py-2 font-mono">{{ $a->investment->reference }}</td><td>{{ $a->investment->user->name }}</td>
                    <td class="text-right font-mono">{{ Money::format($a->amount) }}</td>
                    <td class="text-right">@if($live) @can('cycles.manage')<form method="POST" action="{{ route('admin.trading.cycles.deallocate', $a) }}">@csrf @method('DELETE')<x-btn variant="ghost">Remove</x-btn></form>@endcan @endif</td></tr>
            @empty <tr><td colspan="4" class="py-4 text-center text-gray-500">No capital allocated yet.</td></tr> @endforelse
        </tbody></table>
        @if($open && ! auth()->user()->can('cycles.approve'))<p class="mt-3 text-xs text-gray-500">This cycle is approved. Capital can be reduced or removed here; adding more needs a Director.</p>@endif
        @if($live && ($pending || auth()->user()->can('cycles.approve'))) @can('cycles.manage')
            <form method="POST" action="{{ route('admin.trading.cycles.allocate', $cycle) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t pt-4">@csrf
                <label class="block text-sm font-bold text-gray-700">Policy
                    <select name="investment_id" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal">
                        @foreach($allocatable as $row)<option value="{{ $row['investment']->id }}">{{ $row['investment']->reference }} · {{ $row['investment']->user->name }} (free {{ Money::format($row['free']) }})</option>@endforeach
                    </select></label>
                <x-field name="amount" label="Amount (₦)" inputmode="decimal" required />
                <x-btn>Allocate / update</x-btn>
            </form>
        @endcan @endif
    </x-card>

    {{-- Purchases --}}
    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Purchases</h2>
        <p class="mb-3 text-xs text-gray-500">Proposed by one person, approved by another (a Director above {{ Money::format($threshold) }}), paid by Accounts (not the proposer), then received into stock with a quality check.</p>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr>
            <th class="py-2">Ref</th><th>Supplier</th><th class="text-right">Qty (kg)</th><th class="text-right">Price/kg</th><th class="text-right">Total</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            @forelse($cycle->purchases as $p)
                <tr class="border-b align-top last:border-0">
                    <td class="py-2 font-mono text-xs">{{ $p->reference }}<br><span class="text-gray-500">by {{ $p->proposer?->name }}</span></td>
                    <td>{{ $p->supplier->name }}</td><td class="text-right font-mono">{{ number_format($p->quantity_kg) }}</td>
                    <td class="text-right font-mono">{{ Money::format($p->unit_price) }}</td><td class="text-right font-mono">{{ Money::format($p->total) }}</td>
                    <td>{{ ucfirst($p->status) }}@if($p->payment_reference)<br><span class="text-xs text-gray-500">{{ $p->payment_reference }}</span>@endif</td>
                    <td class="space-y-1">
                        @if($p->status === Purchase::PROPOSED)
                            @can('purchases.approve')<form method="POST" action="{{ route('admin.trading.purchases.approve', $p) }}">@csrf<x-btn>Approve</x-btn></form>@endcan
                        @endif
                        @if(in_array($p->status, [Purchase::PROPOSED, Purchase::APPROVED]))
                            @can('purchases.approve')<form method="POST" action="{{ route('admin.trading.purchases.reject', $p) }}" class="flex gap-1">@csrf<input name="note" placeholder="Reason" required class="w-24 rounded border px-2 py-1 text-xs"><x-btn variant="danger">Reject</x-btn></form>@endcan
                        @endif
                        @if($p->status === Purchase::APPROVED)
                            @can('purchases.pay')<form method="POST" action="{{ route('admin.trading.purchases.pay', $p) }}" class="flex gap-1">@csrf<input name="reference" placeholder="Bank ref" required class="w-24 rounded border px-2 py-1 text-xs"><x-btn>Mark paid</x-btn></form>@endcan
                        @endif
                        @if($p->status === Purchase::PAID)
                            @can('inventory.manage')
                                <form method="POST" action="{{ route('admin.trading.purchases.receive', $p) }}" class="grid grid-cols-2 gap-1 text-xs">@csrf
                                    <input name="quantity_kg" type="number" min="1" max="{{ $p->quantity_kg }}" value="{{ $p->quantity_kg }}" class="rounded border px-1 py-1" title="Received kg">
                                    <input name="warehouse" placeholder="Warehouse" class="rounded border px-1 py-1">
                                    <input name="grade" placeholder="Grade" class="rounded border px-1 py-1">
                                    <input name="moisture_percent" placeholder="Moisture %" inputmode="decimal" class="rounded border px-1 py-1">
                                    <select name="qa_passed" class="rounded border px-1 py-1"><option value="1">QA passed</option><option value="0">QA failed</option></select>
                                    <input name="qa_notes" placeholder="QA notes" class="rounded border px-1 py-1">
                                    <x-btn class="col-span-2">Receive into stock</x-btn>
                                </form>
                            @endcan
                        @endif
                        @if(in_array($p->status, [Purchase::PROPOSED, Purchase::APPROVED]))
                            @can('purchases.propose')<form method="POST" action="{{ route('admin.trading.purchases.cancel', $p) }}">@csrf<x-btn variant="ghost">Cancel</x-btn></form>@endcan
                        @endif
                    </td>
                </tr>
            @empty <tr><td colspan="7" class="py-4 text-center text-gray-500">No purchases yet.</td></tr> @endforelse
        </tbody></table></div>
        @if($open) @can('purchases.propose')
            <form method="POST" action="{{ route('admin.trading.purchases.propose', $cycle) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t pt-4">@csrf
                <label class="block text-sm font-bold text-gray-700">Supplier
                    <select name="supplier_id" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal">@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></label>
                <x-field name="quantity_kg" label="Quantity (kg)" type="number" min="1" required />
                <x-field name="unit_price" label="Price per kg (₦)" inputmode="decimal" required />
                <x-field name="note" label="Note" />
                <x-btn>Propose purchase</x-btn>
            </form>
        @endcan @endif
    </x-card>

    {{-- Stock --}}
    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Stock</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Lot</th><th>Warehouse</th><th>Grade / moisture</th><th>QA</th><th class="text-right">Received (kg)</th><th class="text-right">Remaining (kg)</th><th class="text-right">Remaining cost</th></tr></thead><tbody>
            @forelse($cycle->lots as $l)
                <tr class="border-b last:border-0"><td class="py-2">#{{ $l->id }}</td><td>{{ $l->warehouse ?: '—' }}</td>
                    <td>{{ $l->grade ?: '—' }} / {{ $l->moisture_percent !== null ? $l->moisture_percent.'%' : '—' }}</td>
                    <td>{!! $l->qa_passed ? '<span class="text-green-700">Passed</span>' : '<span class="font-bold text-red-700">Failed</span>' !!}</td>
                    <td class="text-right font-mono">{{ number_format($l->quantity_kg) }}</td><td class="text-right font-mono">{{ number_format($l->remaining_kg) }}</td>
                    <td class="text-right font-mono">{{ Money::format($l->remaining_cost) }}</td></tr>
            @empty <tr><td colspan="7" class="py-4 text-center text-gray-500">Nothing in stock yet.</td></tr> @endforelse
        </tbody></table>
    </x-card>

    {{-- Expenses --}}
    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Cycle expenses</h2>
        <table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Category</th><th>Description</th><th class="text-right">Amount</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($cycle->expenses as $e)
                <tr class="border-b align-top last:border-0"><td class="py-2">{{ ucfirst($e->category) }}</td><td>{{ $e->description }}</td><td class="text-right font-mono">{{ Money::format($e->amount) }}</td>
                    <td>{{ ucfirst($e->status) }}</td>
                    <td class="space-y-1">@if($e->status === CycleExpense::PROPOSED)
                        @can('purchases.pay')<form method="POST" action="{{ route('admin.trading.expenses.pay', $e) }}" class="flex gap-1">@csrf<input name="reference" placeholder="Bank ref" required class="w-24 rounded border px-2 py-1 text-xs"><x-btn>Mark paid</x-btn></form>@endcan
                        @can('purchases.approve')<form method="POST" action="{{ route('admin.trading.expenses.reject', $e) }}">@csrf<x-btn variant="danger">Reject</x-btn></form>@endcan
                    @endif</td></tr>
            @empty <tr><td colspan="5" class="py-4 text-center text-gray-500">No expenses.</td></tr> @endforelse
        </tbody></table>
        @if($open) @canany(['cycles.manage', 'purchases.propose'])
            <form method="POST" action="{{ route('admin.trading.expenses.propose', $cycle) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t pt-4">@csrf
                <label class="block text-sm font-bold text-gray-700">Category
                    <select name="category" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal">@foreach($categories as $c)<option value="{{ $c }}">{{ ucfirst($c) }}</option>@endforeach</select></label>
                <x-field name="description" label="Description" required />
                <x-field name="amount" label="Amount (₦)" inputmode="decimal" required />
                <x-btn>Propose expense</x-btn>
            </form>
        @endcanany @endif
    </x-card>

    {{-- Sales --}}
    <x-card class="mb-6">
        <h2 class="mb-3 font-black text-brand-700">Sales</h2>
        <p class="mb-3 text-xs text-gray-500">Confirming a sale reserves the stock. When the buyer pays into the Investors Profit Account, Accounts marks it paid and the revenue is booked.</p>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-b text-gray-500"><tr><th class="py-2">Ref</th><th>Buyer</th><th>Lot</th><th class="text-right">Qty (kg)</th><th class="text-right">Price/kg</th><th class="text-right">Total</th><th class="text-right">Cost</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($cycle->sales as $s)
                <tr class="border-b align-top last:border-0"><td class="py-2 font-mono text-xs">{{ $s->reference }}</td><td>{{ $s->buyer->name }}</td><td>#{{ $s->inventory_lot_id }}</td>
                    <td class="text-right font-mono">{{ number_format($s->quantity_kg) }}</td><td class="text-right font-mono">{{ Money::format($s->unit_price) }}</td>
                    <td class="text-right font-mono">{{ Money::format($s->total) }}</td><td class="text-right font-mono">{{ $s->status === Sale::DRAFT ? '—' : Money::format($s->cost_of_goods) }}</td>
                    <td>{{ ucfirst($s->status) }}</td>
                    <td class="space-y-1">
                        @if($s->status === Sale::DRAFT) @can('sales.manage')<form method="POST" action="{{ route('admin.trading.sales.confirm', $s) }}">@csrf<x-btn>Confirm</x-btn></form>@endcan @endif
                        @if($s->status === Sale::CONFIRMED) @can('sales.receive')<form method="POST" action="{{ route('admin.trading.sales.receive', $s) }}" class="flex gap-1">@csrf<input name="reference" placeholder="Bank ref" required class="w-24 rounded border px-2 py-1 text-xs"><x-btn>Payment received</x-btn></form>@endcan @endif
                        @if(in_array($s->status, [Sale::DRAFT, Sale::CONFIRMED])) @can('sales.manage')<form method="POST" action="{{ route('admin.trading.sales.cancel', $s) }}">@csrf<x-btn variant="ghost">Cancel</x-btn></form>@endcan @endif
                    </td></tr>
            @empty <tr><td colspan="9" class="py-4 text-center text-gray-500">No sales yet.</td></tr> @endforelse
        </tbody></table></div>
        @if($open) @can('sales.manage')
            <form method="POST" action="{{ route('admin.trading.sales.store', $cycle) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t pt-4">@csrf
                <label class="block text-sm font-bold text-gray-700">Buyer
                    <select name="buyer_id" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal">@foreach($buyers as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
                <label class="block text-sm font-bold text-gray-700">Stock lot
                    <select name="inventory_lot_id" class="mt-1 block rounded border border-gray-300 px-3 py-2 font-normal">
                        @foreach($cycle->lots->where('qa_passed', true)->where('remaining_kg', '>', 0) as $l)<option value="{{ $l->id }}">#{{ $l->id }} · {{ number_format($l->remaining_kg) }} kg left</option>@endforeach</select></label>
                <x-field name="quantity_kg" label="Quantity (kg)" type="number" min="1" required />
                <x-field name="unit_price" label="Price per kg (₦)" inputmode="decimal" required />
                <x-btn>Draft sale</x-btn>
            </form>
        @endcan @endif
    </x-card>

    @if($live) @can('cycles.manage')
        <x-card>
            <h2 class="mb-2 font-black text-brand-700">{{ $pending ? 'Withdraw this proposal' : 'Close or cancel' }}</h2>
            @if($open)
            <p class="mb-3 text-sm text-gray-600">Closing needs every purchase paid and received, every expense settled, all stock sold and every sale paid. It returns the sales proceeds (less the 20% retention) to Main Operations and records the result for the monthly distribution.</p>
            @else<p class="mb-3 text-sm text-gray-600">Withdrawing releases the reserved capital. Nothing has been bought, so no money is affected.</p>@endif
            <div class="flex gap-3">
                @if($open)<form method="POST" action="{{ route('admin.trading.cycles.close', $cycle) }}" data-confirm="Close this cycle and settle the proceeds?">@csrf<x-btn>Close cycle</x-btn></form>@endif
                <form method="POST" action="{{ route('admin.trading.cycles.cancel', $cycle) }}" data-confirm="Cancel this cycle? Only possible if no money has moved.">@csrf<x-btn variant="danger">Cancel cycle</x-btn></form>
            </div>
        </x-card>
    @endcan @endif
</x-layout>
