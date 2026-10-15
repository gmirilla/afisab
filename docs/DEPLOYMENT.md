# Deployment to cPanel

Everything needed is in the [`deploy/`](../deploy) folder. See also [LAUNCH.md](LAUNCH.md) (the full go-live checklist),
[SECURITY.md](SECURITY.md) and [BACKUPS.md](BACKUPS.md).

| File | Purpose |
|---|---|
| `deploy/check-host.php` | Run over SSH on the host first: does it have what AFISAB needs? (PHP version, extensions, memory, uploads, GD, mysqldump) |
| `deploy/build-release.ps1` | Builds the release zip on your PC: compiled assets + production-only PHP dependencies, split into the two cPanel folders. Refuses to build if a `.env`, database, log, KYC file or test would ship |
| `deploy/zip-portable.ps1` | Used by the build: writes a zip with forward-slash paths that unpacks correctly on Linux (Windows' own zip tools write backslashes, which break on cPanel) |
| `deploy/smoke-test.ps1` | Black-box check of a running site from outside: pages and assets load, security headers present, source/secrets/private files unreachable, anonymous visitors redirected, optional real sign-in. Run on staging and after every deploy |
| `deploy/.env.production.example` | Every production setting, with comments |
| `deploy/index.php.cpanel` | Front controller for the split layout (used automatically by the release build) |
| `deploy/crontab.txt` | The three cron jobs: scheduler, queue, nightly backup |
| `deploy/backup.sh`, `deploy/restore-drill.sh` | Nightly backup and the restore test |
| `deploy/.cpanel.yml` | Optional: cPanel Git "Deploy HEAD Commit" template |
| `scripts/concurrency-check.php` | Proof that money cannot be paid twice (needs a throw-away MySQL database) |

## 1. Check the host (DECISIONS.md Q8)

Upload `deploy/check-host.php` anywhere in the account, then over SSH: `php check-host.php`. It must report **0 failures**.
If PHP 8.3+, the `gd`/`intl`/`bcmath`/`mbstring`/`zip` extensions or cron are missing, ask the host to enable them (cPanel:
*Select PHP Version* / *MultiPHP INI Editor*), or choose another host. Delete the file afterwards.

Also needed: HTTPS (AutoSSL), a MySQL/MariaDB database, working outgoing email, and the ability to add cron jobs.

## 2. Folder layout

The application code must sit **outside** the web root:

```
/home/USER/afisab/          the application (app, vendor, .env, storage ...)   <- not reachable from the web
/home/USER/public_html/     the web root: index.php, build/ (CSS+JS), .htaccess, favicon, robots.txt
```

The release zip already contains exactly these two folders (`afisab/` and `public_html/`), with `public_html/index.php`
pointing at `../afisab`. If your folder is not called `afisab`, edit one line in `public_html/index.php`.

## 3. Build and upload

On your PC (Node, Composer and PHP installed):

```
powershell -ExecutionPolicy Bypass -File deploy\build-release.ps1
```

This produces `release\afisab-release-YYYYMMDD-HHMMSS.zip` and a `.sha256` checksum. Upload and unzip it in `/home/USER/`
(cPanel File Manager → Upload → Extract), so you get `/home/USER/afisab` and `/home/USER/public_html`. Compare the checksum
if you like. The script never touches your `.env`, database or live site.

## 4. Configure

1. Create the MySQL database and user in cPanel (*MySQL Databases*); give the user all privileges.
2. `cp deploy/.env.production.example .env` inside `/home/USER/afisab`, then fill in every blank (database, mail, Paystack
   **live** keys only after testing with test keys, bank details for transfers).
3. `php artisan key:generate --force`, **once only**. Copy `APP_KEY` into a password manager now. It encrypts every investor's BVN;
   losing it means the BVNs cannot be read, and changing it later breaks them.
4. Folder permissions: `storage` and `bootstrap/cache` writable by the PHP user.

## 5. Install

```
cd /home/USER/afisab
php artisan migrate --force
php artisan db:seed --force          # ledger accounts, roles, plans, commodities, (inactive) risk limits
php artisan afisab:make-staff "Your Name" you@example.com super-admin
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan afisab:preflight
```

**`afisab:preflight` must report 0 failures before you accept real money.** It checks: production mode, debug off, key set,
HTTPS URL, time zone, MySQL (not SQLite), persistent cache, real mail, secure and encrypted sessions, live Paystack key, real bank
details, writable folders, built assets, migrated database, seeded roles and ledger, an active super-admin, staff two-factor,
risk limits, and the scheduler heartbeat. Use `--strict` to treat warnings as failures.

Then:
- From your PC, run the outside-in check: `powershell -File deploy\smoke-test.ps1 -Base https://DOMAIN` (add `-Email`/`-Password` of a throw-away staff account to test sign-in too). All checks must pass.
- Sign in at `https://DOMAIN/login`; you will be taken to **Account security** to set up two-factor authentication.
- In the Paystack dashboard set the webhook URL to `https://DOMAIN/webhooks/paystack`.
- Add the three cron jobs from `deploy/crontab.txt` (cPanel → *Cron Jobs*). The every-minute scheduler is what runs the **27th profit
  calculation**, maturity reminders and deadline reminders; the queue job sends emails; the third job is the nightly backup.
  After a few minutes the heartbeat check in `afisab:preflight` turns green, and the back office stops showing the warning.

## 6. Configuration reference

| Setting | Why |
|---|---|
| `APP_ENV=production`, `APP_DEBUG=false` | Debug mode would show stack traces and secrets to visitors |
| `APP_TIMEZONE=Africa/Lagos` | "The 27th" and every date are judged in Lagos time |
| `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `SESSION_LIFETIME=60` | Cookies only over HTTPS, encrypted, one-hour idle timeout |
| `CACHE_STORE=database`, `QUEUE_CONNECTION=database` | No Redis on shared hosting; cache must persist (the scheduler heartbeat lives in it) |
| `APP_PUBLIC_PATH=/home/USER/public_html` | **Required for the split layout** (web root separate from the app folder). Tells Laravel where `build/manifest.json` is. Without it every page fails with "Vite manifest not found". Not needed if you point the domain's document root at `afisab/public` instead |
| `AFISAB_REQUIRE_2FA=true` | Staff must use two-factor authentication (on by default in production) |
| `AFISAB_INVESTOR_SHARE_BPS=3000`, `AFISAB_PROFIT_RETENTION_BPS=2000`, `AFISAB_RISK_RESERVE_BPS=500`, `AFISAB_DISTRIBUTION_CUTOFF_DAY=27`, `AFISAB_PURCHASE_DIRECTOR_THRESHOLD=500000000` | The owner's business rules (already the defaults). Change only on the owner's written instruction |

## 7. Updating

Build a new release zip, upload it over the old folders (**keep** `.env` and `storage/`), then:

```
php artisan down
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan afisab:preflight
php artisan up
```

Take a backup first (`sh deploy/backup.sh`). Never run `migrate:fresh` or `db:wipe` on the live database.

## 8. What was verified (2026-10-15)

| Check | Result |
|---|---|
| Full test suite (176 tests, 891 assertions) | Passes on **SQLite** and on **MySQL 9.7 (InnoDB)** |
| Real concurrency: 8 parallel processes per money action on MySQL | Exactly one payment each; balances exact; ledger balanced |
| Backup, then restore into a scratch database | 42 tables identical, ledger balanced; a truncated dump correctly fails |
| `composer audit`, `npm audit` | No known vulnerabilities |
| Release build (`build-release.ps1`) | Produces a zip with production-only dependencies |
| Release zip unpacked like a host would, installed on a fresh MySQL database, cPanel-style split layout (`afisab/` + `public_html/`), production config with cached config/routes/views, then served and tested from outside | **29 of 29 checks pass** (see below). The dry run found and fixed four defects first |
| Pre-flight on the release | Correctly passes the real settings and fails the deliberately test-style ones (http URL, log mailer, insecure cookie, test Paystack key, staff without 2FA) |
| **Not verified** | A real cPanel host (none available), live Paystack, real SMTP, browser rendering of PDFs on the host. Do these in staging |

### Dry-run defects found and fixed (2026-10-15)

Running the real release artifact, instead of only the test suite, found four problems that no automated test could:

1. **Every page would have failed on the live host.** In the split layout Laravel looked for the compiled CSS/JS manifest in the wrong folder ("Vite manifest not found"). Fixed with the `APP_PUBLIC_PATH` setting and a corrected `index.php`; now covered by a test and by the smoke test.
2. **The release zip unpacked wrongly on Linux.** Windows' zip writer stored paths with backslashes, so a Linux host would create files literally named `afisab\app\Foo.php`. Fixed with `zip-portable.ps1`; the build also now takes seconds, not 25 minutes.
3. **A blank bank-details setting passed the pre-flight check.** Now it fails (production) or warns, and is tested.
4. **An unquoted `AFISAB_BANK_NAME=Test Bank` stops the whole app** ("The environment file is invalid"). The `.env` template now quotes it and warns that any value with a space needs quotes.

The 29 outside-in checks that passed (release served on `php -S` over MySQL, production config): home page and CSS/JS load; CSP, X-Frame-Options, nosniff and referrer headers; login, register, verify, forgot-password and health pages; `/admin` and `/investor` redirect anonymous visitors; `/storage/...`, `/.env`, `/composer.json`, `/artisan`, `/vendor/...` are not served; branded 404 leaks nothing; CSRF token on forms; wrong password refused and the session stays anonymous; correct password signs in; a Director without two-factor is held at the security page and the back office stays closed; the session cookie is HttpOnly.
