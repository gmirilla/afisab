# Decisions and open questions

## Decisions made

| Date | Decision | Reason |
|---|---|---|
| 2026-10-14 | Laravel 13 on PHP 8.3+ | Must deploy on cPanel, be secure and efficient; PHP is the native cPanel runtime |
| 2026-10-14 | Database-agnostic code: Eloquent / query builder only, no raw SQL | Owner requirement; works on MySQL, MariaDB, PostgreSQL, SQLite |
| 2026-10-14 | Double-entry ledger, append-only, integer kobo | Auditability required by the account-structure document |
| 2026-10-14 | Blade + Tailwind, minimal JS | Simple to deploy on cPanel (no Node on server) |
| 2026-10-14 | Plans: 3, 6, 12 months; minimum ₦500,000; editable by admin | Owner requirement |
| 2026-10-14 | Payments: Paystack and manual bank transfer both supported | Owner requirement |
| 2026-10-14 | KYC must be approved before an investor can subscribe | Compliance; reduces fraud risk |
| 2026-10-14 | Gateway payments are always re-verified with Paystack server-side | Never trust browser or webhook payload |
| 2026-10-14 | Investor-facing wording says returns are a share of actual profit, not guaranteed | Takaful/profit-sharing positioning and regulatory caution |
| 2026-10-14 | Risk reserve (5%) and profit retention (20%) are config values | Policy may change without code changes |

| 2026-10-14 | **Owner:** 30% of profit to investors, 70% to the company, taken after the 20% retention (Option A) | Q1 answered |
| 2026-10-14 | **Owner:** distribution is monthly; calculated as of the 27th; investors paid by month end | Q1 answered |
| 2026-10-15 | Staff two-factor authentication is mandatory in production and cannot be turned off by staff | Staff move money and see investors' BVNs; a stolen password must not be enough (Q17) |
| 2026-10-15 | A strict Content-Security-Policy: scripts only from this site; no inline scripts or handlers anywhere (a test enforces it) | Defence in depth against cross-site scripting |
| 2026-10-15 | The framework's public route to the private disk is switched off | KYC documents and receipts must only be reachable through permission-checked controllers |
| 2026-10-15 | Restore drills fail closed: anything unreadable is a failure | A drill that can pass vacuously gives false confidence about the backups |
| 2026-10-14 | Risk limits exist for supplier, commodity, cycle size, share of capital at work and the Director threshold, but are seeded **inactive** with suggested values (₦10M / ₦50M / ₦100M / 85% / ₦5M) | I did not want to invent your risk appetite; a Director reviews and switches them on (Q13, Q14) |
| 2026-10-14 | Takaful premiums are paid from the Investors Risk Fund; payroll and trading costs from Main Operations | Per the account-structure document |
| 2026-10-14 | Payroll needs HR to prepare, a Director to approve and Accounts to pay, never the same person twice | Segregation of duties |
| 2026-10-14 | Disabled staff are blocked at login and signed out of open sessions at once; the last active super-admin cannot be disabled or demoted | Prevents lock-out and limits damage from a leaver |
| 2026-10-14 | Quantities are whole kilograms and prices are kobo per kg | Keeps all arithmetic in integers; tonnes/bags can be added as display units |
| 2026-10-14 | A purchase must be approved by someone other than the proposer, within the cycle budget, and paid by someone other than the proposer | Segregation of duties on every cash outflow |
| 2026-10-14 | A cycle only closes when all stock is sold and all items settled; the closing cash movement returns sales proceeds minus the 20% retention to Main Operations | Matches the account document: capital and expenses return to Main Operations, 20% of net profit stays in the Investors Profit Account |
| 2026-10-14 | Profit from a cycle is shared across policies pro rata to the capital allocated to it | Fair and simple; allocation is how Sales chooses which investors fund which trade |
| 2026-10-14 | No payment (profit or capital) can be marked paid unless Main Operations holds enough cash | A bank account cannot go negative; makes shortfalls visible before money moves |
| 2026-10-14 | Capital payouts need maker-checker (preparer ≠ approver) and only apply to `matured` policies; no early exit or rollover | Money-movement control; Q6 unanswered |
| 2026-10-14 | Bank reconciliations are append-only records of statement vs ledger, per account per date | Audit trail; corrections are new records |
| 2026-10-14 | Investors only see profit once a Director has approved the month's run | Never show a figure the company hasn't committed to |
| 2026-10-14 | The person who calculated a run cannot approve it | Maker-checker on money movement |
| 2026-10-14 | A policy's monthly profit is the net of all its cycle results that month; a loss month distributes nothing and is not carried forward | Simplest reading of "net profit"; see Q9 |
| 2026-10-14 | Results recorded after the 27th cut-off roll into the next run | Nothing is ever missed or double-counted |
| 2026-10-14 | Investor return is a share of actual net profit (owner confirmed); no fixed-rate returns | Matches the profit-sharing / Takaful positioning; no guaranteed return |
| 2026-10-14 | Certificate verification uses a random 20-char token, shows masked holder name | Sequential certificate numbers are guessable; protects investor privacy |
| 2026-10-14 | Cycle results are shared pro rata to capital allocated, with largest-remainder rounding | Fair and exact (no kobo created or lost) |

## Assumptions made where the documents were silent

- The 5% risk reserve is moved from Main Operations to the Risk Fund when an investor's payment is
  confirmed (the document states it two ways; see Q3).
- Expenses are deducted when computing net profit and flow back to Main Operations, which pays
  them. Detailed expense posting arrives with the purchasing module.
- A cycle that makes a loss retains nothing (retention is 20% of *positive* net profit only).

## Open questions (need an owner)

| # | Question | Needed for | Owner | Status |
|---|---|---|---|---|
| Q1 | ~~Profit split~~ **ANSWERED 2026-10-14:** share of actual net profit; 30% investors / 70% company; taken from profit *after* the 20% retention; calculated monthly as of the 27th, investors paid by month end. Implemented in milestone 3. | — | Company owner | Closed |
| Q2 | **Missing Account 4.** The account document numbers accounts 1, 2, 3, then 5. Is there a fourth account (e.g. Expenses/Payroll)? | Milestone 4 | Company owner | Open |
| Q3 | **5% risk reserve wording.** Main Operations says "5% of every new investor fund and recycled capital"; Risk Fund says "5% prior to every market purchase". One transfer or two? Is it taken from the investor's principal (so only 95% is traded)? | Milestone 4/5 | Company owner / Accounts | Open |
| Q4 | **What is the 20% retention for?** "Build reserves for future investors profit" implies a profit-smoothing reserve, not company income. Confirm. | Milestone 3/4 | Company owner | Open |
| Q5 | **Regulatory position.** Pooled public investment in Nigeria may fall under SEC rules on collective investment schemes. Takaful implies a Sharia-compliant structure. | Launch | Legal counsel | Open |
| Q6 | **Early exit and rollover.** (Early exit is now built: see Q20. Rollover is not.) Can an investor exit before maturity or roll over into a new plan? Any penalty? | Milestone 3/4 | Company owner | Open |
| Q7 | **Payout approval limits.** Who approves payouts and above what amount (maker-checker thresholds)? | Milestone 4 | Company owner | Open |
| Q9 | **Losses.** If a month nets a loss, is the investor's principal reduced pro rata, covered by the Risk Fund, or protected? Is a loss carried forward and netted against later profit? Today a loss month distributes nothing, is not carried forward, and does not change an investor's value. | Milestone 4/5 and investor wording | Company owner / Legal | Open |
| Q10 | **Which account pays investors' monthly profit?** The document says the Investors Profit Account distributes profits, but also says cash is returned to Main Operations after each cycle. Payments are currently posted from Main Operations. | Milestone 4 | Company owner / Accounts | Open |
| Q12 | **The 5% risk reserve vs returning full principal.** Of a ₦1,000,000 subscription only ₦950,000 stays in Main Operations; ₦50,000 goes to the Risk Fund. At maturity the investor is owed ₦1,000,000, so Main Operations is ₦50,000 short unless trading proceeds have come back (the cash guard will block the payout until they have). Should the Risk Fund top up the difference, or is the 5% not part of the investor's principal at all (so only 95% is traded and 100% returned from proceeds)? Related to Q3. | Milestone 5 / launch | Company owner / Accounts | Open |
| Q11 | **Profit when a policy matures mid-month, or has no cycle capital that month.** Today a policy only earns from cycle results it has capital in. Capital sitting unallocated earns nothing. | Launch | Company owner | Open |
| Q13 | **Director approval threshold for purchases.** I assumed any single purchase above **₦5,000,000** needs a Director (`AFISAB_PURCHASE_DIRECTOR_THRESHOLD`). What should it be, and should expenses and total cycle size have limits too? | Milestone 6 | Company owner | Open |
| Q14 | **Who may allocate investor capital to a cycle, and how much of the pool may be at work at once?** Today Sales can allocate any free capital of an active policy. Many companies cap deployed capital (e.g. keep a liquidity buffer). | Milestone 6 | Company owner | Open |
| Q16 | **Payroll statutory deductions.** PAYE, pension and NHF are entered by HR by hand; the system does not calculate them or remit them to the authorities. Do you want them automated (needs current Nigerian tax tables and your pension administrator) or handled outside the system? | Launch | Company owner / Accountant | Decided 2026-10-17: automate, year-to-date PAYE, per-employee pension administrators, rates as editable versioned data. Built; rates await accountant verification |
| Q17 | **Two-factor authentication for staff.** Now **required for every staff account in production** (`AFISAB_REQUIRE_2FA`, default on). Please confirm; investors may use it voluntarily. | Launch | Company owner | Implemented, awaiting confirmation |
| Q15 | **Export sales and foreign currency.** Buyers pay into the Investors Profit Account in naira only. Dollar receipts into the Domiciliary account, conversion and FX gain/loss are not built. | Later | Company owner / Accounts | Open |
| Q18 | **Profit frequency and losses.** Quarterly and one-off investors have all trading results in their period netted before the 20% / 30% / 70% split, so a weak month is offset by a strong one. Monthly investors do not get that: a loss month pays nothing and is not carried forward. This favours the monthly investor slightly. Is that acceptable, or should every frequency be treated the same (for example, carry monthly losses forward)? Also: is "quarterly" meant from each investor's own start date (built) or calendar quarters? | Launch | Company owner / Legal | Open |
| Q19 | **Losses (agreement clause 7.1).** The template says the investor bears genuine losses in proportion to capital. The software never reduces an investor's principal: a loss month pays no profit and capital is returned in full at maturity. The contract is therefore harsher than the software. Implement loss-bearing (reduce value on a loss cycle), or reword the clause? (Related to Q9 and Q18.) | Before issuing to real investors | Company owner / Legal | Open |
| Q20 | **Early withdrawal (clause 9.2).** BUILT 2026-10-19 on the template's terms: one month's notice (frozen on the policy), subject to funds and settlement of ongoing transactions, as an early maturity that then follows the normal capital payout. Confirm there is no fee or penalty, and that the whole policy (not part of it) matures. | Confirm | Company owner / Legal | Built, to confirm |
| Q21 | **Term (clause 8).** The template says 6 months to 5 years; plans are 3, 6 and 12 months. The agreement prints each policy's actual term and omits the minimum/maximum sentence. Add longer plans or change the clause? | Before issuing | Company owner | Open |
| Q22 | **Schedule items from the sample** (interest-free loan up to 75% of PIA balance, contribution waiver on disability, death cover, monthly instalment, age next birthday) look like a takaful savings product and are not offered, so they are left out. Confirm none apply. | Before issuing | Company owner | Open |
| Q23 | **Policy-number code.** The sample is MIC/06-2026/OYO/0000001. What "OYO" stands for (state, office, occupation location) is unknown, so it is a setting (`AFISAB_POLICY_CODE`; the built-in default is currently NG, the production template says OYO as in the sample). | Before issuing | Company owner | Open |
| Q24 | **Signing.** Built: the investor accepts electronically (tick, time, IP recorded) and the company's signature image is applied automatically. Witness lines appear for printed copies only. Does counsel accept electronic acceptance and a printed MD signature, or should the MD countersign each policy and are witnesses needed? | Before issuing | Legal counsel | Open |
| Q25 | **Shariah wording.** The agreement states Shariah compliance and GIT Takaful. Has a Shariah adviser approved the structure? The public site, emails and certificates use "commodity trading programme"; align the terminology once decided. | Before issuing | Company owner / Shariah adviser | Open |
| Q26 | **Cover artwork.** The cover is rebuilt from the logo. Supply the original cover image (PNG or SVG, not inside a PDF) to use the designed version. | Cosmetic | Company owner | Open |
| Q27 | **Profit shares in clause 6.1** are computed from the current rules: investor 24%, company 56%, risk fund 0%, reserve 20% of net profit (20% retained, then 30/70 of the rest). Confirm these are the intended figures; the risk fund is taken from capital on receipt, not from profit. | Before issuing | Company owner / Accounts | Open |
| Q8 | **Hosting checks.** Run `deploy/check-host.php` over SSH on the chosen cPanel host (SSH, PHP 8.3+, MySQL/MariaDB, cron, extensions, memory, uploads). The tool is ready; a real host has not been tested. | Launch | Developer / host | Open |
