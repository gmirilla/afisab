@props(['title' => null])
@php
    $u = auth()->user();

    // Staff navigation, grouped. Each item: [label, route, visible?]. A group with no visible item is not drawn.
    $groups = [];
    if ($u?->isStaff()) {
        $groups = [
            ['Investors', [
                ['Payments', 'admin.payments', $u->can('payments.confirm')],
                ['KYC review', 'admin.kyc', $u->can('kyc.review')],
                ['Plans', 'admin.plans', $u->can('plans.manage')],
                ['Bank accounts', 'admin.bank-accounts', $u->can('bank.manage')],
                ['Paystack', 'admin.paystack', $u->can('paystack.manage')],
            ]],
            ['Trading', [
                ['Cycles', 'admin.trading.cycles', $u->can('trading.view')],
                ['Stock', 'admin.trading.inventory', $u->can('trading.view')],
                ['Suppliers', 'admin.trading.suppliers', $u->can('trading.view')],
                ['Buyers', 'admin.trading.buyers', $u->can('trading.view')],
            ]],
            ['Treasury', [
                ['Funds', 'admin.funds', $u->can('funds.view')],
                ['Profit runs', 'admin.distributions', $u->canAny(['payouts.prepare', 'ledger.view'])],
                ['Capital payouts', 'admin.payouts', $u->canAny(['payouts.prepare', 'payouts.approve', 'ledger.view'])],
                ['Reconciliation', 'admin.reconciliation', $u->can('ledger.view')],
                ['Reversals', 'admin.reversals', $u->canAny(['reversals.request', 'reversals.approve', 'ledger.view'])],
            ]],
            ['Oversight', [
                ['Risk', 'admin.risk', $u->can('risk.view')],
                ['Reports', 'admin.reports.index', $u->can('reports.view')],
                ['Audit trail', 'admin.audit', $u->can('audit.view')],
            ]],
            ['People', [
                ['Employees', 'admin.hr.employees', $u->can('staff.manage')],
                ['Payroll', 'admin.hr.payroll', $u->canAny(['payroll.manage', 'payroll.approve', 'payroll.pay'])],
                ['Remittances', 'admin.hr.remittances', $u->canAny(['payroll.manage', 'payroll.approve', 'payroll.pay', 'ledger.view'])],
                ['Pension administrators', 'admin.hr.pfas', $u->canAny(['staff.manage', 'payroll.pay'])],
                ['Statutory rates', 'admin.statutory', $u->canAny(['statutory.manage', 'payroll.manage', 'payroll.approve'])],
                ['User accounts', 'admin.users', $u->can('users.manage')],
            ]],
        ];
        $groups = collect($groups)
            ->map(fn ($g) => [$g[0], array_values(array_filter($g[1], fn ($i) => $i[2]))])
            ->filter(fn ($g) => count($g[1]) > 0)->values()->all();
    }
    $investorLinks = [
        ['My investments', 'investor.dashboard', 'investor.dashboard'],
        ['Invest', 'investor.plans', 'investor.plans*'],
        ['Profile & KYC', 'investor.profile', 'investor.profile*'],
    ];
    $active = fn (string $route) => request()->routeIs($route) || request()->routeIs($route.'.*');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}AFISAB</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=lato:400,700,900|playfair-display:600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 font-sans text-gray-800 antialiased">
<header class="sticky top-0 z-40 border-b border-gold-500/40 bg-gradient-to-r from-brand-800 via-brand-700 to-brand-800 text-white shadow-lg shadow-brand-800/20">
    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-2.5 lg:gap-6">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-gradient-to-br from-gold-500 to-gold-600 font-display text-lg font-bold text-brand-800 shadow-inner">A</span>
            <span class="leading-tight">
                <span class="block font-display text-lg font-bold tracking-wide">AFISAB</span>
                <span class="block text-[10px] font-bold uppercase tracking-[0.25em] text-gold-500">Nig. Ltd</span>
            </span>
        </a>

        @auth
            <button type="button" data-nav-toggle aria-expanded="false" aria-label="Menu" class="ml-auto rounded-md p-2 hover:bg-white/10 lg:hidden">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        @endauth

        <div data-nav class="nav-panel @guest always @endguest flex-1 flex-col gap-1 max-lg:absolute max-lg:inset-x-0 max-lg:top-full max-lg:max-h-[80vh] max-lg:overflow-y-auto max-lg:bg-brand-800 max-lg:p-4 max-lg:shadow-xl lg:flex-row lg:items-center lg:gap-1">
            @auth
                <nav class="flex flex-1 flex-col gap-1 lg:flex-row lg:items-center">
                    @if($u->hasRole('investor'))
                        @foreach($investorLinks as [$label, $route, $pattern])
                            <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($pattern) ? 'is-active' : '' }}">{{ $label }}</a>
                        @endforeach
                    @endif
                    @if($u->isStaff())
                        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">Dashboard</a>
                        @foreach($groups as [$groupLabel, $items])
                            @php($groupActive = collect($items)->contains(fn ($i) => $active($i[1])))
                            <div class="relative" data-dropdown>
                                <button type="button" data-dropdown-toggle aria-expanded="false" class="nav-link w-full justify-between {{ $groupActive ? 'is-active' : '' }}">
                                    {{ $groupLabel }}
                                    <svg class="h-3.5 w-3.5 opacity-70" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div class="dd-panel" data-dropdown-panel>
                                    @foreach($items as [$label, $route])
                                        <a href="{{ route($route) }}" class="dd-item {{ $active($route) ? 'is-active' : '' }}">{{ $label }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </nav>

                <div class="relative border-white/10 max-lg:mt-2 max-lg:border-t max-lg:pt-3" data-dropdown>
                    <button type="button" data-dropdown-toggle aria-expanded="false" class="flex w-full items-center gap-2 rounded-full py-1 pl-1 pr-3 hover:bg-white/10">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-gold-500 text-sm font-black text-brand-800">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                        <span class="max-w-32 truncate text-sm font-bold">{{ $u->name }}</span>
                        <svg class="h-3.5 w-3.5 opacity-70" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="dd-panel lg:right-0 lg:left-auto" data-dropdown-panel>
                        <a href="{{ route('account.security') }}" class="dd-item {{ request()->routeIs('account.security*') ? 'is-active' : '' }}">Security &amp; two-factor</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="dd-item w-full text-left">Sign out</button>
                        </form>
                    </div>
                </div>
            @else
                <div class="flex flex-1 items-center justify-end gap-3">
                    <a href="{{ route('login') }}" class="nav-link">Sign in</a>
                    <a href="{{ route('register') }}" class="rounded-md bg-gradient-to-br from-gold-500 to-gold-600 px-4 py-2 text-sm font-bold text-brand-800 shadow hover:brightness-110">Become an investor</a>
                </div>
            @endauth
        </div>

    </div>
</header>

<main class="mx-auto max-w-7xl px-4 py-8 lg:py-10">
    @if(session('status'))
        <div class="mb-5 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900 shadow-sm">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.3a1 1 0 0 0-1.4-1.4L9 10.6 7.7 9.3a1 1 0 0 0-1.4 1.4l2 2a1 1 0 0 0 1.4 0l4-4Z" clip-rule="evenodd"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="mb-5 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-900 shadow-sm">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM9 6a1 1 0 0 1 2 0v4a1 1 0 0 1-2 0V6Zm1 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
            <ul class="space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    {{ $slot }}
</main>

<footer class="mt-8 border-t border-stone-200 py-8 text-center text-xs text-gray-500">
    <p class="font-display text-sm font-semibold text-brand-700">AFISAB NIG. LTD</p>
    <p class="mt-1">Building Trust, Delivering Excellence · <a class="underline decoration-gold-500 underline-offset-2 hover:text-brand-700" href="{{ route('verify.form') }}">Verify a certificate</a></p>
</footer>
</body>
</html>
