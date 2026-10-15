# Development guide

## Setup

```
composer install
copy .env.example .env        (Windows)   |   cp .env.example .env
php artisan key:generate
php artisan migrate --seed    # ledger accounts, roles/permissions, plans
npm install && npm run build  # or `npm run dev` while working on views
php artisan serve
```

Create a staff user: `php artisan afisab:make-staff "Name" you@example.com super-admin`.
Register an investor at `/register`; approve their KYC from a compliance/admin account.

Local mail: set `MAIL_MAILER=log` to read verification links in `storage/logs/laravel.log`.
Local Paystack: use test keys; the webhook needs a public tunnel, but the browser callback verifies
payments without one.

## Tests

```
php artisan test          # 176 tests (in-memory SQLite)

# The same suite on MySQL (use a throw-away database; RefreshDatabase wipes it):
#   PowerShell:  $env:DB_CONNECTION="mysql"; $env:DB_DATABASE="afisab_test"; $env:DB_USERNAME="root"; $env:DB_PASSWORD=""; php artisan test
# Real concurrency proof (needs MySQL/PostgreSQL, WIPES the database named test/concurrency/scratch):
#   $env:AFISAB_CONCURRENCY_WIPE="yes"; $env:DB_DATABASE="afisab_concurrency"; php scripts/concurrency-check.php
php artisan afisab:preflight   # launch readiness checks
vendor/bin/pint           # code style (run before committing)
```

Tests use in-memory SQLite. Test files:

| File | Covers |
|---|---|
| `tests/Feature/LedgerTest.php` | Balanced posting, rollback, immutability, 5% reserve, 20% retention, per-policy balances |
| `tests/Feature/RolesTest.php` | Role permissions |
| `tests/Feature/PerformanceAndCertificateTest.php` | Pro-rata allocation, certificate and statement PDFs, access control, public verification masking, notifications, maturity job |
| `tests/Feature/DistributionTest.php` | 20/30/70 split and rounding, 27th cut-off and roll-over, netting, recalculation, maker-checker, ledger postings, payment, bank CSV, permissions, schedule, reminders, overdue |
| `tests/Feature/HardeningTest.php` | Route audit (public routes fixed; every admin route has a permission), access matrix on every page, security headers and CSP, no inline scripts, private disk not web-reachable, uploads, sign-in logging and throttling, staff 2FA enforcement and set-up, double-submit protection on every money action, duplicate ledger reference, query count, preflight, scheduler heartbeat |
| `tests/Feature/GovernanceTest.php` | Risk limits (each type, validation, Director-only changes), Takaful, payroll (maths, maker-checker, ledger, locking, cash guard), reports and CSV (trial balance, P&L, formula safety), audit viewer, staff accounts (create, roles, disable, last super-admin, login block), work queue |
| `tests/Feature/TradingTest.php` | Allocation limits, purchase maker-checker, budget cap and Director threshold, cash guard, QA stock, oversell protection, exact cost-of-goods, expenses, cycle close and ledger settlement, loss cycle, end-to-end to the monthly distribution, ledger balance, role access, full web flow |
| `tests/Feature/TreasuryTest.php` | Per-policy and as-of balances, funds overview and shortfall, maturity schedule, cash guard, capital payout flow and maker-checker, reconciliation, screens and role access |
| `tests/Feature/InvestmentFlowTest.php` | Registration, KYC, subscription rules, payment confirmation, idempotency, Paystack verify and mismatch, webhook signature, access control |

The Vite build must exist (`npm run build`) for tests that render views.

## Conventions

- Integer kobo for all money; use `App\Support\Money`.
- No raw SQL; stay database-agnostic.
- Put business rules in `app/Services`, keep controllers thin.
- Wrap multi-step money movement in `DB::transaction`.
- Register new polymorphic models in the morph map (`AppServiceProvider`).
- New permissions: add to the seeder, guard routes with `permission:` middleware, add a test that an unauthorised role gets 403.
- Anything a user could be surprised by (state change, money movement) gets an `activity()` log entry.
- No inline `<script>` or `onclick=`/`onsubmit=` in views (the Content-Security-Policy blocks them and a test fails). Use `data-confirm="..."` on a form or `data-autosubmit` on a select; add new behaviour in `resources/js/app.js`.
- A new route under `/admin` must carry `permission:` (or `role:`) middleware or the route-audit test fails. A new public route must be added to the allowed list in `HardeningTest` deliberately.
- Every service method that moves money must re-read the record with `lockForUpdate()` inside `DB::transaction` and check its status (see `PaymentService::confirm`), and needs a stale-copy test.
- Update [MILESTONES.md](MILESTONES.md) in the same change that completes an item.
- Record decisions and unanswered business questions in [DECISIONS.md](DECISIONS.md).
