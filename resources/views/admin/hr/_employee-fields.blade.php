@php
    $naira = fn ($kobo) => $kobo ? number_format($kobo / 100, 2, '.', '') : '';
@endphp
<x-field name="name" label="Name" :value="$e?->name" required />
<x-field name="position" label="Position" :value="$e?->position" />
<x-field name="department" label="Department" :value="$e?->department" />
<x-field name="basic_salary" label="Basic salary / month (₦)" :value="$e ? number_format($e->basic_salary / 100, 2, '.', '') : null" inputmode="decimal" required />
<x-field name="housing_allowance" label="Housing / month (₦, pensionable)" :value="$naira($e?->housing_allowance)" inputmode="decimal" />
<x-field name="transport_allowance" label="Transport / month (₦, pensionable)" :value="$naira($e?->transport_allowance)" inputmode="decimal" />
<x-field name="annual_rent" label="Annual rent paid (₦, for rent relief)" :value="$naira($e?->annual_rent)" inputmode="decimal" />
<x-field name="tax_state" label="PAYE state (tax authority)" :value="$e?->tax_state" placeholder="e.g. Lagos" />
<label class="block text-sm font-bold text-gray-700">Pension administrator
    <select name="pension_administrator_id" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 font-normal">
        <option value="">— none —</option>
        @foreach($administrators as $a)<option value="{{ $a->id }}" @selected((int) old('pension_administrator_id', $e?->pension_administrator_id) === $a->id)>{{ $a->name }}</option>@endforeach
    </select>
</label>
<x-field name="rsa_pin" label="RSA PIN" :value="$e?->rsa_pin" />
<x-field name="nhf_number" label="NHF number" :value="$e?->nhf_number" />
<x-field name="tax_id" label="Tax ID (TIN)" :value="$e?->tax_id" />
<x-field name="email" label="Email" type="email" :value="$e?->email" />
<x-field name="phone" label="Phone" :value="$e?->phone" />
<x-field name="hire_date" label="Hired" type="date" :value="$e?->hire_date?->format('Y-m-d')" />
<x-field name="bank_name" label="Salary bank" :value="$e?->bank_name" />
<x-field name="account_number" label="Account number" :value="$e?->account_number" maxlength="10" />
<x-field name="account_name" label="Account name" :value="$e?->account_name" />
<div class="flex flex-wrap items-end gap-x-5 gap-y-2 pb-2 text-sm md:col-span-4">
    <input type="hidden" name="nhf_applies" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="nhf_applies" value="1" @checked($e ? $e->nhf_applies : true)> Pays NHF</label>
    <input type="hidden" name="pension_exempt" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="pension_exempt" value="1" @checked($e?->pension_exempt)> Pension-exempt</label>
    <input type="hidden" name="paye_exempt" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="paye_exempt" value="1" @checked($e?->paye_exempt)> PAYE-exempt</label>
    @if($e)<label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($e->is_active)> Active</label>@endif
</div>
