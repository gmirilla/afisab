<x-layout title="Reports">
    <h1 class="mb-1 text-2xl font-black text-brand-700">Reports</h1>
    <p class="mb-6 text-sm text-gray-600">Every report can be downloaded as CSV for Excel or Google Sheets.</p>
    <div class="grid gap-4 md:grid-cols-2">
        <x-card><a class="font-black text-brand-700 underline" href="{{ route('admin.reports.investors') }}">Investors and policies</a><p class="mt-1 text-sm text-gray-600">Every policy with amount, status, dates and profit paid / payable.</p></x-card>
        <x-card><a class="font-black text-brand-700 underline" href="{{ route('admin.reports.cycles') }}">Trading cycles</a><p class="mt-1 text-sm text-gray-600">Budget, purchases, sales, net profit and return per cycle.</p></x-card>
        <x-card><a class="font-black text-brand-700 underline" href="{{ route('admin.reports.profit-loss') }}">Profit and loss</a><p class="mt-1 text-sm text-gray-600">The company's income and expenses for a period, after the investors' profit share.</p></x-card>
        <x-card><a class="font-black text-brand-700 underline" href="{{ route('admin.reports.trial-balance') }}">Trial balance</a><p class="mt-1 text-sm text-gray-600">Every ledger account at a date. Debits must equal credits.</p></x-card>
        <x-card><a class="font-black text-brand-700 underline" href="{{ route('admin.reports.distributions') }}">Monthly profit distributions</a><p class="mt-1 text-sm text-gray-600">Each month's net profit, retention, investor and company shares.</p></x-card>
    </div>
</x-layout>
