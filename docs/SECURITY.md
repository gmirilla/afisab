# Security

What protects investor money and data, how each control is tested, and what is still a risk. Read this with
[ROLES.md](ROLES.md) (who can do what) and [LAUNCH.md](LAUNCH.md) (the go-live checklist).

Review date: 2026-10-15 (milestone 7). This was a code-level review with automated tests, **not** an independent
penetration test. Commission one before taking significant investor funds (see "Recommended before launch").

## Controls

### Signing in
| Control | Detail | Test |
|---|---|---|
| Strong passwords | 10+ characters, mixed case, a number; checked against known breaches in production | `InvestmentFlowTest` registration |
| Two-factor authentication | **Required for every staff account** in production (`AFISAB_REQUIRE_2FA`); staff are held at the security page until set up and cannot turn it off. Optional for investors. Recovery codes provided | `HardeningTest` two-factor tests |
| Brute-force throttling | 5 wrong passwords per minute per email+IP, then locked out (even the right password is refused); 30 requests per minute on all sign-in pages | `test_repeated_wrong_passwords_are_throttled` |
| Disabled accounts | Cannot sign in, and an open session is ended on its next request; the last active super-admin cannot be disabled or demoted | `GovernanceTest` |
| Sign-in audit | Every sign-in, sign-out and failed attempt is logged with IP and browser, never the password | `test_sign_ins_and_failures_are_recorded_without_the_password` |
| Email verification | Required before any investor or staff page opens | Fortify |

### Who can do what
| Control | Detail | Test |
|---|---|---|
| Role-based access | 8 roles, 40+ permissions; every `/admin` route demands a permission or role, every `/investor` route demands the investor role | `test_every_back_office_route_demands_a_role_or_permission` |
| Route audit | The list of routes open to the public is fixed in a test; adding a public route fails the build until reviewed | `test_the_only_routes_open_to_the_public_are_the_expected_ones` |
| Every page checked both ways | Anonymous visitors are sent to login from every signed-in page; investors get 403 on every back-office page; staff get 403 on the investor portal | `HardeningTest` |
| Ownership checks | An investor can only see or act on their own policies, payments, receipts and documents (404 otherwise) | `test_an_investor_cannot_touch_another_investors_payment_or_proof` |
| Segregation of duties | The person who proposes cannot approve or pay: purchases, expenses, profit runs, capital payouts, payroll. Directors approve large purchases | Per-module tests |
| Cash guard | No payment (profit, capital, supplier, expense, payroll, premium) can be marked paid if the paying account cannot cover it | `TreasuryTest`, `TradingTest` |

### Protecting data
| Control | Detail |
|---|---|
| BVN encrypted at rest | Laravel encryption keyed by `APP_KEY`. **Losing the key makes every BVN unreadable. Back it up separately from the database** |
| KYC documents and receipts | Stored on a private disk, streamed only to staff with the right permission. The framework's public `/storage/...` route for that disk is switched off (found and fixed in this review) |
| HTTPS only | HSTS header over HTTPS, secure and encrypted session cookies (`SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT`), enforced by `afisab:preflight` |
| Public certificate verification | Random 20-character token (not the sequential number), masked holder name, rate limited |
| Minimal logging | No passwords or BVNs in logs or the audit trail |

### Application attacks
| Attack | Defence |
|---|---|
| SQL injection | Eloquent / query builder with bound parameters everywhere; **no raw SQL in the application** (checked by search) |
| Cross-site scripting | Blade escapes all output; the only two raw outputs are fixed strings; the QR code is library-generated SVG. A strict **Content-Security-Policy** allows scripts only from this site, so even an injected `<script>` cannot run. No inline scripts or event handlers exist (a test fails the build if one is added) |
| Cross-site request forgery | CSRF tokens on every form; the only exception is the Paystack webhook, which is authenticated by HMAC signature |
| Clickjacking | `X-Frame-Options: DENY` and CSP `frame-ancestors 'none'` |
| Mass assignment | Every model declares its fillable fields |
| Malicious uploads | Type (JPG/PNG/PDF) and size (4 MB) enforced server side; files are stored outside the web root and never executed |
| Forged payment notices | Paystack payments are re-verified with Paystack's API (amount and currency) before money is booked; webhook signatures are checked |
| Request flooding | Per-route throttles (sign-in 30/min, signed-in users 240/min, webhook 300/min) |
| Information leakage | Branded 403/404/419/429/500/503 pages; `APP_DEBUG=false` enforced by pre-flight |

### Money integrity
| Control | Detail | Test |
|---|---|---|
| Double-entry ledger | Every posting balances or is rejected; entries and lines cannot be edited or deleted | `LedgerTest` |
| Trial balance | `/admin/reports/trial-balance` proves debits equal credits at any date | `GovernanceTest` |
| No double payment | Every payment action re-reads the record under a row lock and checks its status; ledger references are unique as a second safety net. Verified with stale copies in tests **and with 8 real parallel processes per action against MySQL** (`scripts/concurrency-check.php`): exactly one payment each, balances exact, ledger balanced | `HardeningTest`, concurrency script |
| Integer money | All amounts are whole kobo; no floating-point money | `Money`, ledger tests |
| Audit trail | Every state change is logged with who, what and when; read-only viewer for auditors | `GovernanceTest` |

### Operations
- `php artisan afisab:preflight` fails if the installation is unsafe (debug on, no HTTPS, SQLite, array cache, log mailer, placeholder bank details, test Paystack key, no super-admin, staff without 2FA, unmigrated database...).
- A scheduler **heartbeat** warns the back office if the cron job stops (the 27th profit calculation depends on it).
- Nightly backups with checksum and optional encryption, and a **restore drill** that fails closed ([BACKUPS.md](BACKUPS.md)).
- Dependency audits: `composer audit` and `npm audit` report no known vulnerabilities (2026-10-15). Re-run before every release.

## Known limitations (risk register)

| # | Risk | Severity | Mitigation / recommendation |
|---|---|---|---|
| 1 | **Two staff colluding** can satisfy maker-checker (one proposes, another approves, a third pays) | Medium | Inherent to any system; the audit trail, trial balance and bank reconciliation are the detective controls. Review reconciliations and large payments monthly; keep Director access to 2 or 3 trusted people |
| 2 | A **super-admin** can create users, change roles and switch off risk limits | Medium | Every such action is audit-logged. Limit super-admins; review the audit trail |
| 3 | The back office is reachable from anywhere | Medium | Add an IP allowlist or VPN for staff if the host supports it (`.htaccess` rules) |
| 4 | **No antivirus scan** of uploaded KYC documents | Low-Medium | Files are never executed and are served as downloads with the declared type, but a malicious PDF could harm the reviewer's computer. Reviewers should open documents in a sandboxed viewer; consider ClamAV on the host |
| 5 | CSP allows inline *style attributes* (progress bars) | Low | Scripts are fully locked down; style injection cannot execute code |
| 6 | Password reset and 2FA recovery rely on email | Low-Medium | Protect the mailbox of every staff member with 2FA; recovery codes are shown once per generation |
| 7 | Shared hosting: other accounts on the server, limited control of PHP | Medium | Use a reputable host with account isolation (CloudLinux/CageFS), keep PHP current, consider a VPS once volumes grow |
| 8 | `APP_KEY` loss or leak | High if it happens | Store a copy in a password manager, separate from backups. If it leaks, rotate it and re-encrypt BVNs |
| 9 | No automatic alerts to approvers (email) when something waits | Low | The dashboard "Waiting for you" queue shows it; add email alerts if approvals lag |
| 10 | Early-exit and loss rules are undecided (DECISIONS.md Q6, Q9) | Business | Decide before launch; investor wording must say returns are not guaranteed |
| 11 | Regulatory status of pooled investor funds (SEC) | **High, business** | Obtain legal advice before launch (Q5). The application cannot fix a regulatory gap |

## Recommended before launch
1. Independent penetration test of the staging site (about a week of a specialist's time).
2. Legal review of investor-facing wording, certificates and the investment structure (Q5).
3. Staff security briefing: 2FA, phishing, never sharing logins, how to report a mistake.
4. Run the full UAT script ([UAT.md](UAT.md)) with real staff and Paystack test keys.
5. Run `afisab:preflight` on the live server and the restore drill on the live backup.

## Reporting a problem
Anyone who suspects a security problem or a wrong payment should tell the Director immediately. Do not delete anything: the audit trail and ledger are the evidence.
