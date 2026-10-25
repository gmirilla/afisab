# Backups and recovery

A backup you have never restored is a hope, not a backup. This document covers what is backed up, how, and how to
prove it works.

## What must be backed up

| Item | Where | Why |
|---|---|---|
| **Database** | MySQL/MariaDB | Everything: investors, ledger, payments, audit trail |
| **Private files** | `storage/app/private` | KYC documents and payment receipts (cannot be recreated) |
| **`APP_KEY`** | `.env` | Encrypts NINs. **Without it a restored database has unreadable NINs.** Store it in a password manager, *separately* from the backups |
| `.env` (the rest) | server | Paystack keys, DB password, mail settings. Keep an up-to-date copy in a password manager |
| Code | git / the release zip | Reproducible from the repository; not part of the nightly backup |

## The nightly backup

`deploy/backup.sh` (works for MySQL, MariaDB and PostgreSQL):

- dumps the database consistently (`--single-transaction`, so it never locks or half-writes money),
- adds the private files,
- compresses into one archive `afisab-YYYYMMDD-HHMMSS.tar.gz`, writes a SHA-256 checksum,
- optionally encrypts it with GPG (`GPG_RECIPIENT=...`),
- deletes archives older than `KEEP_DAYS` (default 30),
- refuses to continue if the dump is empty.

Schedule it with the third line of `deploy/crontab.txt` (02:30 daily). **Copy the archives off the server every
night** (a second cPanel account, S3-compatible storage, or a download to the office). A backup on the same disk
does not survive that disk failing, being hacked, or the account being suspended.

> Fixed during the drill: on MySQL servers with GTIDs the dump embedded a setting that made the restore fail.
> `backup.sh` now switches it off automatically.

## Restore drill (do before launch, then every quarter)

```
sh deploy/restore-drill.sh /path/to/afisab-20261015-082110.tar.gz     # decrypt first if you used GPG
```

It restores into a **scratch** database (never the live one), then:
1. checks that **every table** has the same number of rows as the live database,
2. checks that the restored **ledger balances** (debits = credits),
3. confirms the private files archive is present,
4. drops the scratch database.

It **fails closed**: anything it cannot read counts as a failure. During development it was tested against both
a good backup (42 tables compared, ledger balanced, PASSED) and a deliberately truncated one (FAILED, as it must).

Record each drill in the table below.

| Date | Backup tested | Result | By |
|---|---|---|---|
| 2026-10-15 | local MySQL 9.7, 42 tables, test data | PASSED; truncated dump correctly FAILED | development |
| | | | |

## Disaster recovery: restoring for real

1. Provision the new host (see [DEPLOYMENT.md](DEPLOYMENT.md)): PHP, empty database, the release zip.
2. Put the **original `APP_KEY`** and other settings in `.env`. (A new key means unreadable NINs.)
3. Restore the database: `mysql -u USER -p DATABASE < database.sql` (from the archive).
4. Restore files: extract `private-files.tar.gz` into `storage/app/`.
5. `php artisan migrate --force` (applies any newer migrations), then `php artisan afisab:preflight`.
6. Sign in as a super-admin, open **Reports → Trial balance** (must say the ledger balances) and **Funds**; compare with the last bank statements.
7. Re-point the Paystack webhook and the DNS if the address changed.
8. Reconcile each bank account (Reconciliation page) before letting staff pay anyone.

Target: be back within 4 hours, losing at most 24 hours of data (the gap since the last nightly backup; payments in that
gap are recovered from Paystack and bank statements).

## Retention
Keep 30 daily archives on the server side, plus one per month for 12 months off-server. KYC data is personal data:
delete old backups on schedule and protect them as carefully as the live system (encrypt with GPG).
