<x-layout title="Staff">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Staff records</h1>
    <p class="mb-4 text-sm text-gray-600">Employees paid through payroll. A person does not need a login to be on payroll; logins are managed under User accounts.
        Each person has their own <a class="underline" href="{{ route('admin.hr.pfas') }}">pension administrator</a>; pension is worked out on basic + housing + transport. Commission and one-off allowances are taxable but not pensionable.</p>
    <div class="space-y-4">
    @foreach($employees as $e)
        <x-card class="{{ $e->is_active ? '' : 'opacity-70' }}">
            <form method="POST" action="{{ route('admin.hr.employees.save', $e) }}" class="grid gap-3 md:grid-cols-4">@csrf
                @include('admin.hr._employee-fields', ['e' => $e])
                <div class="md:col-span-4"><x-btn>Save {{ $e->name }}</x-btn></div>
            </form>
        </x-card>
    @endforeach
        <x-card>
            <h2 class="mb-3 font-black text-brand-700">Add employee</h2>
            <form method="POST" action="{{ route('admin.hr.employees.save') }}" class="grid gap-3 md:grid-cols-4">@csrf
                @include('admin.hr._employee-fields', ['e' => null])
                <div class="md:col-span-4"><x-btn>Add employee</x-btn></div>
            </form>
        </x-card>
    </div>
</x-layout>
