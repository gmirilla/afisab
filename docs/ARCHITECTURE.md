# Architecture

## Code layout

```
app/
  Actions/Fortify/          Registration (creates investor role + profile), password rules
  Console/Commands/         afisab:make-staff
  Enums/                    InvestmentStatus, PaymentStatus, PaymentMethod, KycStatus
  Exceptions/               LedgerException (integrity), BusinessRuleException (safe to show users)
  Http/Controllers/
    Investor/               Investment, Payment, Profile (investor portal)
    Admin/                  Dashboard, Payment, Kyc, Plan (back office)
    Webhooks/               PaystackWebhookController
  Models/                   Investment, InvestmentPlan, InvestorProfile, Payment, LedgerAccount,
                            JournalEntry, JournalLine, User
  Services/
    Ledger/                 LedgerService (posting, balances), InvestorFundsService (business rules)
    Payments/               PaymentService (state machine), PaystackClient (gateway)
    Performance/            PerformanceService (cycle results -> per-policy entries, position), ProfitSplit (20% / 30% / 70% rules),
                            DistributionService (monthly run: calculate, approve, pay, export, reminders)
    Documents/              DocumentService (certificate and statement PDFs via dompdf, QR via endroid)
    Trading/                CycleService (open, allocate, cancel, close), PurchaseService (propose/approve/pay/receive),
                            ExpenseService, SaleService (draft/confirm/receive payment)
    Risk/                   RiskService (limits, enforcement, dashboard figures), TakafulService
    Hr/                     PayrollService (adjustments, prepare, approve, pay)
    Reports/                ReportService (investors, cycles, trial balance, P&L, distributions)
    Treasury/               TreasuryService (funds overview, per-policy rows, maturity schedule, cash guard, reconciliation),
                            CapitalPayoutService (prepare / approve / reject / pay principal at maturity)
    MaturityService         Daily reminders and active -> matured
    SubscriptionService     Subscribe and cancel with rule checks
  Support/Money.php         Naira string <-> integer kobo, formatting
config/afisab.php           Business rules and bank details
database/migrations         Schema
database/seeders            Ledger accounts, roles/permissions, plans
resources/views             Blade views and components (layout, card, field, btn)
routes/web.php              All routes
```

## Rules the code follows

- **Money is integer kobo.** Never floats. `Money::fromNaira()` parses user input.
- **Database-agnostic.** Eloquent and query builder only; no `DB::raw`, `whereRaw` or vendor SQL. Grouped totals
  (e.g. balance per policy) are summed in PHP over a lazy cursor (`LedgerService::balancesBySubject`) rather than with SQL `GROUP BY`/`SUM` expressions; revisit if the ledger grows to millions of lines.
  `lockForUpdate()` is used on payments and investments (a no-op on SQLite, a real lock on MySQL/PostgreSQL).
- **Ledger is append-only.** Journal entries and lines throw on update or delete. Corrections are reversing entries.
- **Business logic lives in services**, not controllers. Controllers validate, call a service, and
  translate `BusinessRuleException` into form errors.
- **Morph map is enforced** (`AppServiceProvider`): polymorphic columns store `investment`, `user`,
  `payment`, … not class names. Register any new model that is used polymorphically (including as an
  activity-log subject) or Laravel will throw.
- **Authorization:** route middleware (`role:`, `permission:`) plus ownership checks in investor
  controllers. `super-admin` passes every gate via `Gate::before`.
- **Audit trail:** `activity()` is called on state changes (subscription, payment confirm/reject, KYC, plans).

## Data model

```
users ──1:1── investor_profiles   (KYC: phone, DOB, address, encrypted BVN, bank, ID path, status)
users ──1:n── investments ──n:1── investment_plans
investments ──1:n── payments       (method: paystack|transfer; status; proof_path)
investments ──1:n── performance_entries  (per-cycle share of result; distribution_run_id set when swept into a run)
risk_limits (key, value, is_active), takaful_policies
employees ──1:n── payroll_adjustments; payroll_runs ──1:n── payroll_lines   (draft -> approved -> paid)
users.disabled_at                                   (set = cannot sign in; EnsureUserIsActive ends open sessions)
commodities, suppliers, buyers                      (reference data)
trade_cycles ──1:n── cycle_allocations ──n:1── investments   (which policies' capital funds a cycle)
trade_cycles ──1:n── purchases ──1:1── inventory_lots         (purchase paid -> received -> lot with QA)
trade_cycles ──1:n── cycle_expenses, sales ──n:1── inventory_lots, buyers
   statuses: cycle open|closed|cancelled; purchase proposed->approved->paid->received (or rejected/cancelled);
             sale draft->confirmed->paid (or cancelled); expense proposed->paid (or rejected)
investments ──1:n── capital_payouts   (prepared -> approved -> paid | rejected)
ledger_accounts ──1:n── bank_reconciliations  (statement balance vs ledger balance at date; append-only)
distribution_runs ──1:n── distribution_lines ──n:1── investments
   run: period YYYY-MM, cutoff (27th), pay_by (month end), status calculated -> approved -> paid
   line: monthly net, retained, investor_share, company_share, status pending|payable|paid|none

ledger_accounts ──1:n── journal_lines ──n:1── journal_entries
journal_lines.subject  (polymorphic)  -> the investment (policy) the money belongs to
journal_entries.subject (polymorphic) -> the originating record
```

Investment status: `pending_payment → active → matured → paid_out`, or `pending_payment → cancelled`.
(`matured` and `paid_out` transitions arrive in milestone 4.)

Payment status: `pending → awaiting_confirmation → confirmed`, or `rejected` / `failed`.

## Key flows

### Subscription and activation
1. Investor with approved KYC submits plan + amount → `SubscriptionService::subscribe()` checks plan active, KYC, minimum; creates investment (`pending_payment`) with the plan duration copied.
2. Investor pays:
   - **Paystack:** `PaymentService::startPaystack()` creates a payment and redirects to checkout. On return (`/investor/payments/callback`) and on webhook (`/webhooks/paystack`, HMAC-SHA512 verified) the service calls `settlePaystack()`, which re-verifies with the gateway and checks amount and currency.
   - **Transfer:** `startTransfer()` → investor uploads receipt (`submitProof()`) → Accounts calls `confirm()` after checking the bank.
3. `PaymentService::confirm()` (one DB transaction, row-locked, idempotent): marks payment confirmed, activates the investment, sets start/maturity, issues certificate number, voids other open payments, and calls `InvestorFundsService::receive()`.

### Ledger postings on receipt (per policy)
1. Dr Main Operations / Cr Investor Funds Held — full amount.
2. Dr Risk Fund / Cr Main Operations — 5% (config).

See [ACCOUNTING.md](ACCOUNTING.md).

### Certificates and verification
- On confirmation the policy gets `certificate_number` (`AFC-YYYY-000123`, human reference) and `certificate_token` (random, unguessable).
- `/investments/{id}/certificate` and `/statement` stream PDFs to the owner or staff with `certificates.issue`.
- The QR code and printed code point to `/verify/{token}`, a public, rate-limited page that shows a masked holder name (`A*** O***`), plan, amount, dates and status, and nothing else.

### Trading cycle
`open` → allocate capital → purchases (maker-checker, budget cap, cash guard) → receive into stock (QA) → sales
(confirm reserves stock) → buyer pays → `close` (all items settled, stock sold) → ledger settlement + per-policy
performance entries → next monthly distribution. See [ACCOUNTING.md](ACCOUNTING.md) for the postings. Controllers use the
`AttemptsActions` trait so rule violations come back as form errors.

### Risk limits
`RiskService::limit($key)` returns the value only when the limit is switched on. It is called from
`PurchaseService::approve()` (supplier and commodity exposure, Director threshold) and `CycleService::allocate()`
(cycle size, share of capital at work). Exposure counts approved, paid or received purchases in open cycles, so it
falls when a cycle closes.

### Work queue and audit
The back-office dashboard lists only the items the signed-in person has permission to act on (purchases to approve or
pay, runs and payouts, payroll, KYC and payments). Every state change calls `activity()`; `/admin/audit` is a
read-only view of that log.

### Scheduled jobs (need the cron entry in DEPLOYMENT.md)
| Command | When | What |
|---|---|---|
| `afisab:process-maturities` | daily 06:00 | Maturity reminder once, 14 days before; marks due policies `matured` |
| `afisab:run-distribution` | 27th, 23:30 | Calculates the month's profit distribution run |
| `afisab:distribution-reminders` | daily 09:00 | Catches a missed calculation; approval and payment deadline reminders |

### Monthly distribution
See [ACCOUNTING.md](ACCOUNTING.md). `calculate()` is idempotent and re-runnable until the run is approved;
`approve()` enforces maker-checker and posts the ledger liability; `markLinePaid()` posts the cash movement.
Each step is one DB transaction with row locks.

## Security notes

- Passwords: min 10 chars, mixed case, numbers; breach check in production.
- Login and 2FA are rate-limited (Fortify limiters). 2FA is available to all users; staff should enable it.
- BVN encrypted at rest (Laravel `encrypted` cast, keyed by `APP_KEY` — **back up the key**; losing it makes BVNs unreadable).
- KYC documents and payment receipts are stored on the private `local` disk and streamed only to authorised staff.
- CSRF is on everywhere except the Paystack webhook, which is authenticated by signature.
