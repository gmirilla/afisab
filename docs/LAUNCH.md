# Launch checklist

Work top to bottom. Nothing in section 1 is a software task: each needs the owner or a professional.

## 1. Decisions and approvals (blockers)

| ✔ | Item | Owner | Reference |
|---|---|---|---|
| ☐ | **Legal and regulatory opinion** on taking pooled investor funds (SEC, Takaful structure), and on the wording of the site, certificates and statements | Legal counsel | DECISIONS Q5 |
| ☐ | Early exit and rollover: allowed, or not? | Owner | Q6 |
| ☐ | Who bears a **loss**: investors, Risk Fund, or principal protected? Wording must match | Owner / legal | Q9 |
| ☐ | Which account pays investors' monthly profit | Owner / accounts | Q10 |
| ☐ | The 5% risk reserve vs returning full principal | Owner / accounts | Q3, Q12 |
| ☐ | Director purchase threshold and other **risk limits** switched on (they ship OFF) | Directors | Q13, Q14 |
| ☐ | Payroll statutory rates: accountant verifies the seeded draft (PAYE bands, rent relief, pension, NHF, due dates), then the super-admin activates it under **Statutory rates**; enter each staff member's pension administrator, RSA PIN, PAYE state and exemptions; decide how to handle staff paid earlier this tax year (opening balances not built) | Owner / accountant | Q16 |
| ☐ | Missing "Account 4" in the account structure document | Owner | Q2 |
| ☐ | Named staff for each role; at least two Directors | Owner | ROLES.md |
| ☐ | Paystack **live** account approved; settlement bank account confirmed | Finance | |
| ☐ | Live keys entered at **Treasury > Paystack** (super-admin), connection test passed, webhook and callback URLs set in the Paystack dashboard | Director | |
| ☐ | Enter the investor-payment bank account(s) in the back office: **Treasury > Bank accounts** (super-admin). Overrides `AFISAB_BANK_*` once one is saved | Director | |
| ☐ | Corporate bank accounts opened as the account-structure document requires (Main Operations, Risk Fund, Investors Profit, Domiciliary) | Finance | |
| ☐ | Independent penetration test done, findings fixed | IT | SECURITY.md |
| ☐ | UAT signed off ([UAT.md](UAT.md)) | Department heads | |

## 2. Hosting

| ✔ | Item |
|---|---|
| ☐ | Host runs PHP 8.3+ with the required extensions: upload `deploy/check-host.php` and run `php check-host.php` over SSH (0 failures) |
| ☐ | HTTPS certificate installed; the domain redirects `http` to `https` |
| ☐ | MySQL database and user created; backups off-server arranged |
| ☐ | Email sending works (SPF/DKIM set up for the domain so verification emails do not land in spam) |
| ☐ | Cron jobs added from `deploy/crontab.txt` (scheduler, queue, backup) |

## 3. Deploy (see [DEPLOYMENT.md](DEPLOYMENT.md))

1. Build the release: `powershell -File deploy\build-release.ps1`; upload `afisab/` and `public_html/`.
2. Fill in `.env` from `deploy/.env.production.example`; `php artisan key:generate --force` **once**; save `APP_KEY` in a password manager.
3. `php artisan migrate --force && php artisan db:seed --force`.
4. `php artisan afisab:make-staff "Name" email super-admin`; sign in; set up two-factor.
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
6. Set the Paystack webhook to `https://DOMAIN/webhooks/paystack`.
7. **`php artisan afisab:preflight` must show 0 failures.** Fix every warning you can. Then from your PC run `powershell -File deploy\smoke-test.ps1 -Base https://DOMAIN`: every check must pass.
8. In the back office: create staff users, review and switch on the **risk limits**, review the plans (₦500,000 minimum; 3, 6, 12 months), enter suppliers/buyers.
9. Run **one real end-to-end payment** with a small live amount (₦500,000 plan is the minimum; use a director's own money), confirm the certificate, the ledger and the bank statement agree, then reconcile.
10. Take a backup and run the **restore drill** on it ([BACKUPS.md](BACKUPS.md)).

## 4. Day of launch
- Two people on call (one technical, one finance) for the first week.
- Watch the dashboard banner: it warns if the scheduler cron stops.
- Check Funds and Reconciliation every day for the first month.

## 5. The first 27th
The first monthly profit calculation is the highest-risk day. On the 27th evening check the run appeared; the Director reviews and approves on the 28th; Accounts pays by month end; reconcile on the 1st.
If the run did not appear, press **Calculate** on the Profit runs page (it does the same thing) and fix the cron.

## 6. Rolling back
- **Code problem:** re-upload the previous release zip's `afisab/` and `public_html/` folders, `php artisan migrate:status` (do not roll back migrations that already hold real data), `php artisan config:cache`.
- **Data problem:** put the site in maintenance mode (`php artisan down`), restore the last good backup ([BACKUPS.md](BACKUPS.md)), reconcile, `php artisan up`.
- **A wrong payment:** never edit the ledger; open the entry (Funds, then the account, then the entry reference) and request a reversal with a reason; a Director approves it under **Reversals**. This fixes the ledger only: also correct the business record behind it (e.g. reject or re-do the payout) and note it in the audit trail.

## 7. After launch (first 90 days)
Review the audit trail weekly; run the restore drill; re-run `composer audit` and `npm audit`; decide the open questions; plan the next release (invoices/delivery notes, export sales and foreign currency, PDF reports, statutory payroll).
