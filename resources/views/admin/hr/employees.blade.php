@php use App\Support\Money; @endphp
<x-layout title="Staff">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Staff records</h1>
    <p class="mb-4 text-sm text-gray-600">Employees paid through payroll. A person does not need a login to be on payroll; logins are managed under Users.</p>
    <div class="space-y-4">
    @foreach($employees as $e)
        <x-card>
            <form method="POST" action="{{ route('admin.hr.employees.save', $e) }}" class="grid gap-3 md:grid-cols-4">@csrf
                <x-field name="name" label="Name" :value="$e->name" required />
                <x-field name="position" label="Position" :value="$e->position" />
                <x-field name="department" label="Department" :value="$e->department" />
                <x-field name="basic_salary" label="Basic salary / month (₦)" :value="number_format($e->basic_salary / 100, 2, '.', '')" required />
                <x-field name="email" label="Email" type="email" :value="$e->email" />
                <x-field name="phone" label="Phone" :value="$e->phone" />
                <x-field name="hire_date" label="Hired" type="date" :value="$e->hire_date?->format('Y-m-d')" />
                <x-field name="tax_id" label="Tax ID" :value="$e->tax_id" />
                <x-field name="bank_name" label="Bank" :value="$e->bank_name" />
                <x-field name="account_number" label="Account number" :value="$e->account_number" maxlength="10" />
                <x-field name="account_name" label="Account name" :value="$e->account_name" />
                <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($e->is_active)> Active</label>
                <div class="md:col-span-4"><x-btn>Save</x-btn></div>
            </form>
        </x-card>
    @endforeach
        <x-card>
            <h2 class="mb-3 font-black text-brand-700">Add employee</h2>
            <form method="POST" action="{{ route('admin.hr.employees.save') }}" class="grid gap-3 md:grid-cols-4">@csrf
                <x-field name="name" label="Name" required /><x-field name="position" label="Position" /><x-field name="department" label="Department" />
                <x-field name="basic_salary" label="Basic salary / month (₦)" inputmode="decimal" required />
                <x-field name="email" label="Email" type="email" /><x-field name="phone" label="Phone" /><x-field name="hire_date" label="Hired" type="date" /><x-field name="tax_id" label="Tax ID" />
                <x-field name="bank_name" label="Bank" /><x-field name="account_number" label="Account number" maxlength="10" /><x-field name="account_name" label="Account name" />
                <div class="flex items-end"><x-btn>Add employee</x-btn></div>
            </form>
        </x-card>
    </div>
</x-layout>
