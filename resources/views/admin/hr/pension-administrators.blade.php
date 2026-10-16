<x-layout title="Pension administrators">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Pension administrators</h1>
    <p class="mb-4 text-sm text-gray-600">The PFAs your staff have chosen. After each payroll, the pension owed is grouped per administrator (employee and employer shares together) so Accounts can pay each one and send the schedule. Assign each person's administrator on their <a class="underline" href="{{ route('admin.hr.employees') }}">staff record</a>.</p>
    <div class="space-y-4">
    @foreach($administrators as $a)
        <x-card class="{{ $a->is_active ? '' : 'opacity-70' }}">
            <form method="POST" action="{{ route('admin.hr.pfas.save', $a) }}" class="grid gap-3 md:grid-cols-6">@csrf
                <x-field name="name" label="Administrator" :value="$a->name" class="md:col-span-2" required />
                <x-field name="bank_name" label="Remittance bank" :value="$a->bank_name" />
                <x-field name="account_number" label="Account number" :value="$a->account_number" maxlength="10" />
                <x-field name="account_name" label="Account name" :value="$a->account_name" />
                <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($a->is_active)> Active</label>
                <div class="flex items-center justify-between md:col-span-6"><span class="text-xs text-gray-500">{{ $a->employees_count }} staff</span>@can('staff.manage')<x-btn>Save</x-btn>@endcan</div>
            </form>
        </x-card>
    @endforeach
    @can('staff.manage')
        <x-card>
            <h2 class="mb-3 font-black text-brand-700">Add administrator</h2>
            <form method="POST" action="{{ route('admin.hr.pfas.save') }}" class="grid gap-3 md:grid-cols-6">@csrf
                <x-field name="name" label="Administrator" class="md:col-span-2" required />
                <x-field name="bank_name" label="Remittance bank" /><x-field name="account_number" label="Account number" maxlength="10" /><x-field name="account_name" label="Account name" />
                <div class="flex items-end"><x-btn>Add</x-btn></div>
            </form>
        </x-card>
    @endcan
    </div>
</x-layout>
