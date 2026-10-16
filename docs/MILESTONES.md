# Milestones

Status key: ✅ done · 🔶 in progress · ⬜ not started · ⛔ blocked

Last updated: 2026-10-15 (milestone 7 engineering complete)

## Summary

| # | Milestone | Status | Notes |
|---|---|---|---|
| 1 | Foundation and ledger core | ✅ | Completed 2026-10-14 |
| 2 | Investor onboarding, subscriptions, payments | ✅ | Completed 2026-10-14 |
| 3 | Certificates, performance, monthly profit distribution | ✅ | 30/70 split after 20% retention; calculated 27th, paid by month end |
| 4 | Treasury and funds visibility | ✅ | Funds, per-policy view, ledger drill-down, capital payouts, reconciliation, cash guard |
| 5 | Trading cycles, procurement, inventory, sales | ✅ | Allocation, purchases, stock/QA, expenses, sales, close; feeds monthly distribution |
| 6 | Risk controls, HR, reports, audit | ✅ | Limits (seeded off), Takaful, payroll, reports + CSV, audit viewer, staff accounts, work queue |
| 7 | Hardening and launch | 🔶 | Engineering complete; waiting on legal sign-off, owner decisions, UAT and a real-host deployment |

## 1. Foundation and ledger core ✅

- [x] Laravel 13 project, Fortify, spatie permission and activity log
- [x] Double-entry ledger (accounts, journal entries and lines), immutable, integer kobo
- [x] `LedgerService`: balanced posting in a transaction, balances overall and per policy
- [x] `InvestorFundsService`: 5% risk reserve on receipt; cycle settlement with 20% retention
- [x] Seeded ledger accounts and 8 roles with permissions
- [x] Rules configurable in `config/afisab.php`
- [x] Tests for ledger and roles

## 2. Investor onboarding, subscriptions, payments ✅

- [x] Registration, email verification, password reset, 2FA sign-in challenge
- [x] KYC submission (BVN encrypted, private ID upload) and compliance review
- [x] Investment plans (3 / 6 / 12 months, ₦500,000 minimum), admin editable
- [x] Subscription with KYC and minimum checks; duration snapshot on the policy
- [x] Paystack payment: initialize, callback verify, signed webhook, amount check
- [x] Bank-transfer payment: receipt upload, Accounts confirm/reject
- [x] Confirmation activates policy, sets dates, issues certificate number, books ledger
- [x] Investor portal and staff back-office screens
- [x] `afisab:make-staff` command
- [x] Feature tests (23 total passing)
- [ ] UI for investors to enable/disable 2FA (Fortify endpoints exist; no screen yet)
- [ ] Mail driver configured for verification and reset emails (environment task)
- [ ] Run test suite against MySQL (database-agnostic claim not yet verified beyond SQLite)

## 3. Certificates, performance and monthly profit distribution ✅

- [x] Certificate PDF with QR code (landscape A4, issued on payment confirmation)
- [x] Public verification page `/verify/{token}` and `/verify` form; unguessable token, masked holder name, rate limited
- [x] Investor dashboard: principal, profit share credited, current value, term progress, days to maturity
- [x] Per-policy performance history: `performance_entries`, cycle results shared pro rata (largest-remainder, sums exactly)
- [x] Policy statement PDF
- [x] Notifications: payment confirmed (with certificate details), maturity approaching (once, 14 days before)
- [x] Daily `afisab:process-maturities` job: reminders and `active → matured`
- [x] **Profit split decided (2026-10-14):** 20% retained, then 30% investors / 70% company (`ProfitSplit`, config in `config/afisab.php`)
- [x] **Monthly distribution run** (`DistributionService`): calculated as of the **27th** (end of day), approved by a Director (maker-checker), paid by month end (29th/30th/31st)
  - [x] Sweeps all unassigned results up to the cut-off; anything later rolls into next month; results netted per policy per month
  - [x] Scheduled 27th 23:30 (`afisab:run-distribution`) plus daily 09:00 catch-up and deadline reminders (`afisab:distribution-reminders`)
  - [x] Recalculate while unapproved; approved runs are locked
  - [x] Approval books the liability (Dr Trading Income / Cr Investor Profit Payable); payment moves cash (Dr Payable / Cr Main Operations)
  - [x] Bank CSV export (formula-injection safe), mark lines or whole run paid with a bank reference
  - [x] Staff notifications (ready, urgent approval, payment due) and investor "profit paid" email; OVERDUE flag after month end
  - [x] Staff screens (`/admin/distributions`) and investor view of monthly shares, next calculation date and payment deadline
- [x] 22 new tests (62 total passing)
- [ ] Screen for staff to record a cycle result (arrives with milestone 5 trade cycles; until then `PerformanceService::recordCycleResult()` is the entry point, so no run has real data yet)
- [ ] Statement CSV export
- [ ] Visual check of the PDFs on the production host (fonts, QR) during deployment dry run
- [ ] Confirm which account pays investors (Q10)
- ⛔ Loss treatment: Q9

## 4. Treasury and funds visibility ✅ (core), with items waiting on owner answers

- [x] Funds screen `/admin/funds`: Main Ops, Risk Fund, Profit and Domiciliary balances, capital held, obligations due in 30 days, **available after obligations** (turns red on a shortfall)
- [x] Funds **per investment policy**: capital held, how much sits in Main Ops and in the Risk Fund, profit payable, principal due within 30 days, with totals
- [x] Principal falling due by month (maturity schedule)
- [x] Ledger drill-down per account `/admin/ledger/{code}` with date and policy filters and pagination
- [x] Capital payout at maturity: Accounts prepares, a Director approves (different person), Accounts pays; posts Dr Investor Funds Held / Cr Main Operations, closes the policy as `paid_out`, emails the investor
- [x] **Cash guard:** no profit or capital payment can be marked paid if Main Operations cannot cover it
- [x] Bank reconciliation: record a statement balance against the ledger balance at the end of that date; differences are highlighted; records are permanent
- [x] `ledger.view` auditors can see everything but change nothing; new `reconciliation.manage` permission for Accounts
- [x] 22 new tests (84 total passing)
- [x] Reversal (correcting) entries: see milestone 7 addendum
- [ ] Reconciliation by uploading a bank statement file (today the balance is typed in)
- [ ] Account 4 defined (DECISIONS.md Q2)
- [ ] Domiciliary account postings and FX
- ⛔ Early exit / rollover (Q6) and what covers the 5% risk reserve at maturity (Q12)

## 5. Trading cycles, procurement, inventory, sales ✅ (core)

- [x] Commodity catalogue seeded from the product portfolio (20 commodities)
- [x] Suppliers (farmers, aggregators) and buyers, with bank/contact details; managed by Procurement and Sales respectively
- [x] **Trading cycle** `/admin/trading/cycles`: open, allocate investor capital (a policy's capital cannot be double-allocated across open cycles), close or cancel
- [x] **Purchases:** propose, approve (different person; Director above ₦5,000,000; cannot exceed the cycle budget), pay (Accounts, not the proposer, cash guard), receive into stock with quality check
- [x] **Stock** `/admin/trading/inventory`: lots with warehouse, grade, moisture %, QA pass/fail; failed stock cannot be sold; cost tracked per lot
- [x] **Cycle expenses:** transport, storage, labour, packaging; proposed by one person, paid by another
- [x] **Sales:** draft, confirm (reserves stock, fixes cost of goods; last sale takes the rounding remainder so cost is exact), buyer payment received by Accounts into the Investors Profit Account; cancel returns stock
- [x] **Closing a cycle:** blocked until everything is settled; returns proceeds less the 20% retention to Main Operations, records the result per policy for the monthly 30/70 distribution
- [x] Capital payout blocked while a policy's capital is deployed in an open cycle
- [x] Full ledger stays balanced; end-to-end tests from allocation to monthly distribution
- [x] 29 new tests (113 total passing)
- [ ] Sales invoices / delivery notes as PDFs
- [ ] Export sales: documentation, foreign-currency receipts into the Domiciliary account (with FX)
- [ ] Partial cycle close / selling from several lots in one sale
- [ ] Quantities in tonnes/bags (everything is whole kilograms today)
- [ ] Notifications to approvers when a purchase or expense is waiting
- ⛔ Risk limits (per supplier, per commodity, per cycle) belong to milestone 6

## 6. Risk controls, HR, reports, audit ✅

- [x] **Risk limits** `/admin/risk` (Directors set, accounts/auditors view): supplier exposure, commodity exposure, cycle size, share of investor capital at work, Director purchase threshold. Enforced at purchase approval and capital allocation. **Seeded inactive with suggested values; nothing is enforced until a Director switches them on**
- [x] Risk dashboard: capital at work, Risk Fund cover of investor capital, cash available after 30-day obligations, largest supplier/commodity/cycle exposures
- [x] **Takaful policies:** record cover, pay premiums from the Risk Fund (Dr Takaful Expense / Cr Risk Fund), expiry warnings
- [x] **HR:** employee records (salary, bank, tax ID); monthly **payroll** with allowances, commissions and manual deductions; HR prepares, a Director approves (different person), Accounts pays (not the preparer); bank CSV; ledger postings (gross expense, net cash, deductions payable); cash guard
- [x] **Reports** `/admin/reports` with CSV download: investors and policies, trading cycles, profit and loss (after investor share), trial balance at a date (proves debits = credits), monthly distributions
- [x] **Audit trail viewer** `/admin/audit` with filters; read-only
- [x] **Staff accounts** `/admin/users`: create with emailed set-password link, change role, disable/enable (signs the person out immediately; blocks login); cannot change or disable yourself; last super-admin protected
- [x] **"Waiting for you" work queue** on the back-office dashboard, per permission
- [x] 32 new tests (145 total passing)
- [ ] PDF export of reports (CSV only today)
- [x] Statutory payroll maths (PAYE, pension, NHF): see milestone 7 addendum of 2026-10-17 (Q16)
- [ ] Enforce two-factor authentication for staff (milestone 7)
- [ ] Email alerts to approvers (the dashboard queue is the only prompt today)
- [x] Reversal (correcting) ledger entries: see milestone 7 addendum

## 7. Hardening and launch 🔶 (engineering done; business and hosting sign-offs outstanding)

Engineering work, completed 2026-10-15:

- [x] **Security review** with a written control list and risk register ([SECURITY.md](SECURITY.md)). One real finding fixed: the framework exposed a public `/storage/...` route for the private disk that holds KYC documents and receipts (now off, tested)
- [x] **Route audit tests:** the exact set of public routes is fixed in a test; every `/admin` route must carry a permission or role; anonymous, investor and wrong-role access is tested on every page
- [x] **Staff two-factor authentication enforced** (default on in production): self-service set-up page with QR code, recovery codes, can't be switched off by staff
- [x] **Browser protections:** Content-Security-Policy (scripts only from this site; inline handlers removed and guarded by a test), HSTS, clickjacking, MIME-sniffing, referrer and permissions headers
- [x] **Rate limits** on sign-in pages, signed-in users and the webhook; brute-force lock-out tested
- [x] **Sign-in audit:** sign-ins, sign-outs and failures logged (never the password)
- [x] **Branded error pages** that leak nothing
- [x] **Double-submit protection tested on every money action** (payment confirm, supplier payment, expense, buyer payment, profit approval/payment, capital payout, payroll, Takaful premium) plus a ledger duplicate-reference test
- [x] **Real concurrency test on MySQL** (`scripts/concurrency-check.php`): 8 parallel processes per action; exactly one payment each; balances exact; ledger balanced
- [x] **Database portability verified on MySQL 9.7 (InnoDB)**: all 176 tests pass on SQLite and MySQL
- [x] **Efficiency:** per-policy funds view does not query once per policy (tested)
- [x] **Backups and restore drill** (`deploy/backup.sh`, `deploy/restore-drill.sh`) run for real. The drill found and fixed two defects (GTID dumps that would not restore; a check that could pass vacuously). It now fails closed ([BACKUPS.md](BACKUPS.md))
- [x] **Launch pre-flight** `php artisan afisab:preflight` (23 checks) and **scheduler heartbeat** with a back-office warning if the cron stops
- [x] **Deployment kit** (`deploy/`): host checker, release builder, production `.env` template, cron lines, front controller for the cPanel layout
- [x] **Release dry run** on the built artifact (zip unpacked like a host would; production-only dependencies; fresh MySQL database; cPanel-style split layout; production config; served and tested from outside: **29/29 checks pass**). It found four defects no test could, all fixed: (1) in the split layout Laravel looked for the compiled CSS/JS manifest in the wrong folder, so **every page would have failed on the live host** (`APP_PUBLIC_PATH` + corrected front controller); (2) the Windows-built zip used backslash paths that unpack wrongly on Linux (`zip-portable.ps1`); (3) blank bank-transfer details passed pre-flight; (4) an unquoted bank name with a space breaks the `.env`. See DEPLOYMENT.md
- [x] `deploy/smoke-test.ps1`: outside-in check to run on staging and after every deploy
- [x] Dependency audits clean (`composer audit`, `npm audit`)
- [x] Launch checklist ([LAUNCH.md](LAUNCH.md)) and user acceptance test script ([UAT.md](UAT.md))
- [x] 31 new tests (176 total passing)

Addendum, 2026-10-15: **ledger reversals** (`/admin/reversals`, `/admin/ledger/entry/{id}`)

- [x] Click any entry reference in an account's ledger to open it; Accounts (`reversals.request`) can request a reversal with a reason
- [x] A Director (`reversals.approve`, a different person) approves; the system posts a mirror entry `REV-<reference>` with every line flipped and the same policy subjects. Rejecting changes nothing
- [x] The original is never touched; an entry can be reversed once; a reversal cannot be reversed
- [x] A reversal that would leave Main Operations, Risk Fund, Profit or Domiciliary below zero is refused and rolled back
- [x] Requests and decisions are in the audit trail; pending requests show in the Director's "Waiting for you" queue
- [x] Corrects the **ledger only**: it does not undo the payment, payout or other record behind the entry (the screen says so)
- [x] 10 new tests

Addendum, 2026-10-16: **payment bank accounts** (`/admin/bank-accounts`)

- [x] Super-admin (`bank.manage`) adds and edits the accounts investors pay into; several can be active; each can carry a note
- [x] Current password required on every save; 10-digit number validated; changes audited with old and new values
- [x] Accounts are switched off, never deleted; the last active one cannot be switched off
- [x] Investors see the active accounts on the payment page; the `AFISAB_BANK_*` settings are the fallback until the first account is saved; pre-flight checks the live accounts
- [x] 6 new tests

Addendum, 2026-10-16: **Paystack management** (`/admin/paystack`, super-admin only)

- [x] Secret and public keys saved in the database, encrypted; shown masked (`sk_live_••••abcd`); `.env` values are the fallback until saved
- [x] Password required to save; format and test/live pairing validated; changes audited with masked values (never the keys)
- [x] On/off switch: off blocks new online payments (and hides the button) but callback, webhook and re-check keep settling payments already started
- [x] Test connection (records pass/fail and time); status cards show ON/OFF, LIVE/TEST (warns on test keys in production)
- [x] Shows the webhook and callback URLs to paste into the Paystack dashboard
- [x] Recent online payments, with "Re-check with Paystack" for pending ones (recovers a missed webhook)
- [x] Pre-flight reads the saved key; 9 new tests

Addendum, 2026-10-17: **statutory payroll: PAYE, pension, NHF** (Q16 built)

- [x] Rates live in **versioned rule sets** (`/admin/statutory`): draft, then activate with the super-admin's password, then locked for good. A change in law is a new set with a later effective date; each payroll run records the set it used
- [x] Seeded with one **unverified draft** (Nigeria Tax Act 2025 bands, 8%/10% pension, 2.5% NHF). Nothing is calculated until the super-admin, with the accountant, checks and activates it. **No active set = payroll behaves exactly as before** (HR enters deductions by hand)
- [x] **PAYE by the year-to-date method**: tax to date on the bands scaled to the months elapsed, less PAYE already withheld, so variable commission corrects itself within the year. Rent relief with a cap; employee pension and NHF are deductible
- [x] Pension on basic + housing + transport; NHF on basic; employer pension is an extra expense. Per-employee: administrator, RSA PIN, NHF number, pension/NHF/PAYE exemptions, annual rent, PAYE state
- [x] **Each staff member has their own pension administrator**; payroll will not prepare for someone who owes pension but has none (or is marked exempt)
- [x] Paying posts PAYE, Pension and NHF to their own payable accounts and creates **remittances**: PAYE per state, pension per administrator (employee + employer), NHF to FMBN, with due dates from the rule set
- [x] `/admin/hr/remittances`: schedule CSV per remittance, Accounts records the bank reference (not the payroll's preparer, cash guard, posts Dr payable / Cr Main Operations); overdue flag; item in the Accounts work queue
- [x] An earlier month of the year left as a draft blocks the next one (its figures are needed for the year-to-date)
- [x] 23 new tests, with the key PAYE figures worked by hand
- [ ] **Opening balances** for staff who were paid earlier in the tax year outside this system (go-live mid-year): year-to-date starts at the first payroll here
- [ ] Payslips (PDF); NSITF / ITF / NHIS employer levies; per-line manual override of a computed amount
- [ ] Accountant to verify every seeded figure before activation

Outstanding, not engineering:

- [ ] **Legal/regulatory sign-off** on investor wording, structure and KYC (DECISIONS.md Q5)
- [ ] **Owner decisions** that change behaviour: Q2, Q3/Q12, Q6, Q9, Q10, Q13, Q14, Q16
- [ ] **Run the UAT script** with real staff on staging and sign it off ([UAT.md](UAT.md))
- [ ] **Deploy to the real cPanel host** and run `check-host.php`, `afisab:preflight`, a live small-amount payment and the restore drill there (no cPanel host was available to test)
- [ ] **Independent penetration test** (recommended)
- [ ] Switch on the risk limits (Directors)
- [ ] Verify PDF rendering and live Paystack/SMTP on the host

## Change log

| Date | Change |
|---|---|
| 2026-10-14 | Milestones 1 and 2 completed; documentation added |
| 2026-10-14 | Milestone 3 completed: certificates, verification, performance tracking, statements, notifications, maturity job. Profit split left configurable |
| 2026-10-15 | Milestone 7 engineering completed: security review and fixes, enforced staff 2FA, CSP and headers, route audit tests, concurrency proof on MySQL, MySQL test run, backup and restore drill, preflight, heartbeat, deployment kit, launch and UAT documents |
| 2026-10-14 | Milestone 6 completed: risk limits, Takaful, payroll, reports, audit viewer, staff account management, work queue |
| 2026-10-14 | Milestone 5 completed: trading cycles, purchases with maker-checker and budget cap, stock with QA, expenses, sales, cycle close feeding the monthly distribution |
| 2026-10-14 | Milestone 4 completed: funds and per-policy views, ledger drill-down, capital payouts with maker-checker, reconciliation, cash guard |
| 2026-10-14 | Owner decided profit split (30/70 of profit after 20% retention) and monthly cadence (calculate 27th, pay by month end). Per-cycle crediting replaced by the monthly distribution run |
