# AFISAB platform documentation

AFISAB NIG. LTD is a Nigerian agricultural commodity trading company. This application lets
investors subscribe to time-bound investment plans and lets staff run the funds, purchasing and
trading that those investments finance.

Stack: Laravel 13, PHP 8.3+ (developed on 8.4), Blade + Tailwind 4 (Vite), spatie/laravel-permission,
spatie/laravel-activitylog, Laravel Fortify. Database-agnostic (no raw SQL).

| Document | What it covers |
|---|---|
| [MILESTONES.md](MILESTONES.md) | Phase plan, status and checklists. **Update this as work lands.** |
| [DECISIONS.md](DECISIONS.md) | Decisions made, assumptions, and open questions that need an owner |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Code layout, data model, key flows |
| [ACCOUNTING.md](ACCOUNTING.md) | Ledger design and the business rules taken from the account-structure document |
| [ROLES.md](ROLES.md) | Roles, permissions and the route-to-permission map |
| [DEPLOYMENT.md](DEPLOYMENT.md) | cPanel deployment kit, environment settings, cron, what was verified |
| [LAUNCH.md](LAUNCH.md) | Go-live checklist: owner/legal blockers, hosting, deploy steps, first 27th, rollback |
| [UAT.md](UAT.md) | User acceptance test script for every role, with sign-off |
| [SECURITY.md](SECURITY.md) | Controls, how each is tested, risk register, what to do before launch |
| [BACKUPS.md](BACKUPS.md) | Backups, the restore drill and disaster recovery |
| [DEVELOPMENT.md](DEVELOPMENT.md) | Local setup, tests, conventions |

Source documents (provided by the company): *AFISAB Executive Summary* and
*Recommended Corporate Bank Account Structure*.

## Current status

See [MILESTONES.md](MILESTONES.md). Short version: Phase 1 (foundation and ledger) and Phase 2
(investor onboarding, subscriptions, payments) and Phase 3 (certificates, verification, performance
tracking, statements, notifications, monthly profit distribution) are complete. Investors receive 30% of
net profit after the 20% retention, calculated as of the 27th of each month and paid by month end.
Phase 4 (funds and per-policy views, ledger drill-down, capital payouts at maturity, reconciliation, cash
guard) is complete. Phase 5 (trading cycles, purchases, stock with quality checks, expenses, sales, cycle close)
is complete and feeds the monthly distribution. Phase 6 (risk limits, Takaful, payroll, reports with CSV,
audit viewer, staff account management, work queue) is complete. Phase 7 (hardening and launch) is
**engineering-complete**: what remains is legal sign-off, owner decisions, UAT and a deployment to the real host. See
[LAUNCH.md](LAUNCH.md).
