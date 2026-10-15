@props(['title' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}AFISAB</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=lato:400,700,900" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased">
<header class="bg-brand-700 text-white">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
        <a href="{{ route('home') }}" class="text-lg font-black tracking-wide">AFISAB <span class="text-gold-500">NIG. LTD</span></a>
        <nav class="flex flex-1 flex-wrap gap-4 text-sm">
            @auth
                @php($u = auth()->user())
                @if($u->hasRole('investor'))
                    <a href="{{ route('investor.dashboard') }}" class="hover:underline">My investments</a>
                    <a href="{{ route('investor.plans') }}" class="hover:underline">Invest</a>
                    <a href="{{ route('investor.profile') }}" class="hover:underline">Profile &amp; KYC</a>
                @endif
                @if($u->isStaff())
                    <a href="{{ route('admin.dashboard') }}" class="hover:underline">Dashboard</a>
                    @can('payments.confirm') <a href="{{ route('admin.payments') }}" class="hover:underline">Payments</a> @endcan
                    @can('trading.view')
                        <a href="{{ route('admin.trading.cycles') }}" class="hover:underline">Trading</a>
                        <a href="{{ route('admin.trading.inventory') }}" class="hover:underline">Stock</a>
                        <a href="{{ route('admin.trading.suppliers') }}" class="hover:underline">Suppliers</a>
                        <a href="{{ route('admin.trading.buyers') }}" class="hover:underline">Buyers</a>
                    @endcan
                    @can('funds.view') <a href="{{ route('admin.funds') }}" class="hover:underline">Funds</a> @endcan
                    @canany(['payouts.prepare', 'ledger.view']) <a href="{{ route('admin.distributions') }}" class="hover:underline">Profit runs</a> @endcanany
                    @canany(['payouts.prepare', 'payouts.approve', 'ledger.view']) <a href="{{ route('admin.payouts') }}" class="hover:underline">Capital payouts</a> @endcanany
                    @canany(['reversals.request', 'reversals.approve', 'ledger.view']) <a href="{{ route('admin.reversals') }}" class="hover:underline">Reversals</a> @endcanany
                    @can('ledger.view') <a href="{{ route('admin.reconciliation') }}" class="hover:underline">Reconciliation</a> @endcan
                    @can('risk.view') <a href="{{ route('admin.risk') }}" class="hover:underline">Risk</a> @endcan
                    @can('reports.view') <a href="{{ route('admin.reports.index') }}" class="hover:underline">Reports</a> @endcan
                    @can('staff.manage') <a href="{{ route('admin.hr.employees') }}" class="hover:underline">Staff</a> @endcan
                    @canany(['payroll.manage', 'payroll.approve', 'payroll.pay']) <a href="{{ route('admin.hr.payroll') }}" class="hover:underline">Payroll</a> @endcanany
                    @can('audit.view') <a href="{{ route('admin.audit') }}" class="hover:underline">Audit</a> @endcan
                    @can('users.manage') <a href="{{ route('admin.users') }}" class="hover:underline">Users</a> @endcan
                    @can('kyc.review') <a href="{{ route('admin.kyc') }}" class="hover:underline">KYC</a> @endcan
                    @can('plans.manage') <a href="{{ route('admin.plans') }}" class="hover:underline">Plans</a> @endcan
                @endif
            @endauth
        </nav>
        @auth
            <a href="{{ route('account.security') }}" class="text-sm hover:underline">Security</a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="text-sm underline">Sign out ({{ auth()->user()->name }})</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="text-sm hover:underline">Sign in</a>
            <a href="{{ route('register') }}" class="rounded bg-gold-500 px-3 py-1 text-sm font-bold text-brand-800">Become an investor</a>
        @endauth
    </div>
</header>
<main class="mx-auto max-w-6xl px-4 py-8">
    @if(session('status'))
        <div class="mb-4 rounded border border-green-300 bg-green-50 px-4 py-3 text-green-800">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-red-800">
            <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    {{ $slot }}
</main>
<footer class="py-6 text-center text-xs text-gray-500">AFISAB NIG. LTD · Building Trust, Delivering Excellence ·
    <a class="underline" href="{{ route('verify.form') }}">Verify a certificate</a></footer>
</body>
</html>
