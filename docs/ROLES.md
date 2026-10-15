# Roles and permissions

Defined in `database/seeders/RolesAndPermissionsSeeder.php`. Run `php artisan db:seed --class=RolesAndPermissionsSeeder`
after changing it (safe to re-run). `super-admin` receives every permission and passes every gate.

| Role | Permissions | Typical user |
|---|---|---|
| `super-admin` | all, including `plans.manage`, `users.manage`, `payouts.approve` | Director / system owner |
| `accounts` | `funds.view`, `ledger.view`, `payments.confirm`, `payouts.prepare`, `reconciliation.manage`, `reversals.request`, `reports.view`, `investors.view`, `purchases.approve`, `purchases.pay`, `sales.receive`, `trading.view` | Finance |
| `sales` | `cycles.manage`, `sales.manage`, `purchases.propose`, `buyers.manage`, `trading.view`, `funds.view`, `investors.view` | Trading / sales |
| `procurement` | `purchases.propose`, `inventory.manage`, `suppliers.manage`, `trading.view` | Procurement / warehouse |
| `hr` | `staff.manage`, `payroll.manage` | Human resources |
| (accounts also) | `risk.view`, `takaful.manage`, `payroll.pay` | |
| `compliance` | `kyc.review`, `investors.view`, `certificates.issue` | Investor relations / compliance |
| `auditor` | `funds.view`, `ledger.view`, `reports.view`, `audit.view`, `investors.view` | Read-only auditor |
| `investor` | none (portal only, own data) | Investors |

## Route access today

| Area | Routes | Requires |
|---|---|---|
| Investor portal | `/investor/*` | signed in, verified email, role `investor`; policy ownership checked per record |
| Back-office dashboard | `/admin` | any staff role; funds panel only with `funds.view` |
| Payments | `/admin/payments*` | `payments.confirm` |
| KYC review | `/admin/kyc*` | `kyc.review` |
| Plans | `/admin/plans*` | `plans.manage` |
| Distribution runs: view | `/admin/distributions*` | `payouts.prepare` or `ledger.view` (accounts, auditors, super-admin) |
| Distribution runs: calculate, recalculate, export CSV, mark paid | `/admin/distributions*` (POST/export) | `payouts.prepare` (accounts) |
| Distribution runs: approve | `POST /admin/distributions/{run}/approve` | `payouts.approve` (super-admin only); cannot approve a run you calculated |
| Trading: view cycles, stock, suppliers, buyers | `/admin/trading/*` (GET) | `trading.view` (accounts, sales, procurement, auditors) |
| Trading: open/close/cancel cycle, allocate capital | `POST /admin/trading/cycles...` | `cycles.manage` (sales) |
| Purchases: propose, cancel | | `purchases.propose` (sales, procurement) |
| Purchases: approve, reject (and reject expenses) | | `purchases.approve` (accounts, super-admin); not the proposer; Director only above the threshold |
| Purchases and expenses: mark paid | | `purchases.pay` (accounts); not the proposer |
| Receive goods into stock with QA | | `inventory.manage` (procurement) |
| Sales: draft, confirm, cancel | | `sales.manage` (sales) |
| Sales: record buyer payment | | `sales.receive` (accounts) |
| Expenses: propose | | `cycles.manage` or `purchases.propose` |
| Suppliers / buyers: manage | | `suppliers.manage` (procurement) / `buyers.manage` (sales) |
| Risk page (view) | `/admin/risk` | `risk.view` (accounts, auditors, super-admin) |
| Change risk limits | `POST /admin/risk/limits` | `risk.manage` (super-admin only) |
| Record Takaful policy, pay premium | `POST /admin/risk/takaful...` | `takaful.manage` (accounts) |
| Employees | `/admin/hr/employees` | `staff.manage` (hr) |
| Payroll: view | `/admin/hr/payroll*` | `payroll.manage`, `payroll.approve`, `payroll.pay` or `ledger.view` |
| Payroll: adjustments, prepare | | `payroll.manage` (hr) |
| Payroll: approve | | `payroll.approve` (super-admin); not the preparer |
| Payroll: pay, bank CSV | | `payroll.pay` (accounts); not the preparer |
| Reports and CSV downloads | `/admin/reports/*` | `reports.view` (accounts, auditors, super-admin) |
| Audit trail | `/admin/audit` | `audit.view` (auditors, super-admin) |
| Staff accounts | `/admin/users` | `users.manage` (super-admin) |
| Account security (two-factor set-up) | `/account/security` | any signed-in user; **staff are held here until two-factor is confirmed** (when enforced) |
| Funds overview and per-policy funds | `/admin/funds` | `funds.view` (accounts, sales, auditors, super-admin) |
| Ledger drill-down, reconciliation list | `/admin/ledger/{code}`, `/admin/reconciliation` | `ledger.view` (accounts, auditors, super-admin) |
| Record a reconciliation | `POST /admin/reconciliation` | `reconciliation.manage` (accounts) |
| Ledger entry page | `/admin/ledger/entry/{id}` | `ledger.view` |
| Reversals: list | `/admin/reversals` | `reversals.request`, `reversals.approve` or `ledger.view` |
| Reversals: request | `POST /admin/ledger/entry/{id}/reverse` | `reversals.request` (accounts) |
| Reversals: approve, reject | `POST /admin/reversals/{id}/approve|reject` | `reversals.approve` (super-admin); not the person who requested it |
| Capital payouts: view | `/admin/payouts` | `payouts.prepare`, `payouts.approve` or `ledger.view` |
| Capital payouts: prepare, mark paid | `POST /admin/payouts/...` | `payouts.prepare` (accounts) |
| Capital payouts: approve, reject | `POST /admin/payouts/{id}/approve|reject` | `payouts.approve` (super-admin); not the person who prepared it |
| Certificate / statement PDFs | `/investments/{id}/certificate`, `/statement` | signed in; owner, or `certificates.issue` (compliance) |
| Certificate verification | `/verify`, `/verify/{token}` | public, rate limited (20/min) |
| Paystack webhook | `/webhooks/paystack` | valid HMAC signature (no session) |

Permissions such as `cycles.manage`, `inventory.manage`, `staff.manage` are seeded for later
milestones; no routes use them yet.

## Creating staff

The first super-admin is created from the command line:

```
php artisan afisab:make-staff "Full Name" email@example.com super-admin
```

After that, a super-admin manages staff at **Users**: create an account (the person receives an emailed link to set
their own password), change a role, or disable the account (blocks sign-in and ends open sessions immediately). You
cannot change or disable your own account, and the last active super-admin is protected. Staff should enable
two-factor authentication after first sign-in (not yet enforced; DECISIONS.md Q17).

## Planned controls (milestone 4 and 6)

- Maker-checker: one person prepares a payout/purchase, a different person approves.
- Amount thresholds that require a Director.
- Audit log viewer for the `auditor` role.
