# Accounting and ledger

Source: *Recommended Corporate Bank Account Structure* (company document). This file records how
each rule is implemented and where the document is ambiguous.

## Reversals

- Accounts opens an entry and requests a reversal with a reason; a Director (different person) approves; the system posts `REV-<original reference>` with each line's direction flipped and the same policy subjects, so per-policy balances return too.
- The original entry is never changed. One reversal per entry; a reversal cannot be reversed (post a correct entry instead).
- Refused, and rolled back, if it would leave Main Operations, Risk Fund, Investors Profit or Domiciliary below zero. When undoing a multi-entry event (e.g. a payment confirmation posts a receipt and then the 5% reserve transfer), reverse the newest entry first.
- It corrects the ledger only; the business record (payment, payout, distribution) must be fixed separately.

## Ledger accounts (seeded)

| Code | Name | Type | Purpose |
|---|---|---|---|
| `MAIN_OPS` | Main Operations Account | asset | Primary current account. All investor funds enter here first. Pays suppliers, farmers, transporters, staff, expenses, allowances, commissions. |
| `RISK_FUND` | Investors Risk Fund | asset | Receives 5% of investor funds. Risk cushion, Takaful premiums, warehouse and equipment projects. |
| `INVESTOR_PROFIT` | Investors Profit Account | asset | Receives buyer proceeds. Deducts capital and expenses, retains 20% of net profit, returns the rest to Main Operations. |
| `DOMICILIARY` | Domiciliary Account (USD/GBP/EUR) | asset | Export proceeds and foreign-currency payments. |
| `INVENTORY` | Commodity Inventory | asset | Commodities held at cost. |
| `INVESTOR_FUNDS` | Investor Funds Held | liability | Capital owed to investors. |
| `INVESTOR_PROFIT_PAYABLE` | Investor Profit Payable | liability | Approved monthly profit shares owed to investors until paid. |
| `TRADING_EXPENSES` | Trading Expenses | expense | Transport, storage, labour and packaging costs of trading cycles. |
| `INSURANCE_EXPENSE` | Takaful / Insurance Expense | expense | Takaful premiums, paid from the Investors Risk Fund. |
| `PAYROLL_EXPENSE` | Payroll Expense | expense | Salaries, allowances and commissions (gross). |
| `STATUTORY_PAYABLE` | Payroll Deductions Payable | liability | Tax, pension and other deductions withheld, owed to the authorities. |
| `TRADING_INCOME` | Trading Income | income | Gross trading margin on sales. |

The document's numbering skips account 4. See DECISIONS.md Q2.

## How the ledger works

- Every movement is one **journal entry** with two or more **lines**; total debits must equal total credits or the post is rejected and nothing is saved.
- Entries and lines are **immutable**. A mistake is corrected with a reversing entry (`ReversalService`): Accounts requests it, a Director approves, the system posts the mirror `REV-<reference>`. See "Reversals" below.
- Amounts are integer kobo.
- A balance is derived: debit-normal accounts (assets, expenses) = debits − credits; others = credits − debits.
- Each line can carry a **subject** (the investment/policy it belongs to). This is how "funds available per investor policy" is calculated: `LedgerService::balance($account, $investment)`.
- Percentages use basis points and round **down** (`intdiv`), so the system never allocates more than it holds.

## Rules implemented

| Document rule | Implementation |
|---|---|
| All investor funds first enter the Main Operations account | `InvestorFundsService::receive()` debits `MAIN_OPS` first |
| 5% of every new investor fund goes to the Investors Risk Fund | Second entry `MAIN_OPS → RISK_FUND` for `risk_reserve_bps` (500) |
| Investors Profit Account receives buyer funds | `settleCycle()` debits `INVESTOR_PROFIT` with sales proceeds |
| Deduct capital and all expenses from sales proceeds | Net profit = proceeds − capital − expenses (never below zero) |
| Retain 20% of actual net profit per completed cycle | `profit_retention_bps` (2000) of net profit stays in `INVESTOR_PROFIT` |
| Capital and balance of profits returned to Main Operations | Transfer of proceeds − retained back to `MAIN_OPS` |
| Track investor-funded transactions separately | Per-policy subject on ledger lines |

## Worked example (₦1,000,000, 6-month plan)

On payment confirmation:

| Entry | Debit | Credit | Amount |
|---|---|---|---|
| Receipt | Main Operations | Investor Funds Held | ₦1,000,000 |
| Risk reserve | Risk Fund | Main Operations | ₦50,000 |

Main Operations now holds ₦950,000 for this policy; the Risk Fund holds ₦50,000.

Cycle settlement (sales ₦1,300,000, capital ₦1,000,000, expenses ₦100,000):

- Net profit = 1,300,000 − 1,000,000 − 100,000 = ₦200,000
- Retained (20%) = ₦40,000, stays in Investors Profit Account
- Distributable = ₦160,000, shared in the monthly distribution run (below)
- ₦1,260,000 returns to Main Operations

## Performance tracking (per policy)

When a trading cycle closes, `PerformanceService::recordCycleResult($cycleRef, [investmentId => capital], $netProfit)`
shares the cycle's net profit across the funding policies **pro rata to capital** (largest-remainder
rounding, so parts sum exactly) and stores one `performance_entries` row per policy. Recording a
result does **not** credit anyone; investor profit is decided only by the monthly run.

## Monthly profit distribution

Owner decisions (2026-10-14): investors receive **30%** and the company **70%** of net profit **after the
20% retention**. Everything is calculated **as of the 27th** of each month so investors can be paid by
**month end** (29th, 30th or 31st; the 28th in a non-leap February).

| When | What | Who |
|---|---|---|
| 27th, 23:30 (scheduled `afisab:run-distribution`) | Sweep every unassigned trading result recorded up to the end of the 27th into the month's run; net them per policy; apply the split | System |
| 27th to 29th | Review, recalculate if needed, **approve** (must be a different person from whoever calculated) | Director |
| On approval | Books the liability to each investor | System |
| By month end | Export the bank CSV, pay investors, mark paid with the bank reference | Accounts |
| Daily 09:00 | Catch a missed calculation; chase approval after the 27th and payment in the last 2 days | System |

Per policy, with monthly net profit N (all rounding down; the company takes the remainder):

```
retained  = 20% of N                 (stays in the Investors Profit Account)
remaining = N - retained
investor  = 30% of remaining         (paid to the investor)
company   = remaining - investor     (about 70%)
```

No profit (N <= 0) distributes nothing. Retained + investor + company always equals N.

Example, N = ₦200,000: retained ₦40,000, remaining ₦160,000, investor ₦48,000, company ₦112,000.

Ledger postings (per policy, per run):

| Step | Debit | Credit | Amount |
|---|---|---|---|
| Approval | Trading Income | Investor Profit Payable | investor share |
| Payment | Investor Profit Payable | Main Operations | investor share (Q10: confirm the paying account) |

The company's 70% needs no posting; it stays in Main Operations.

Investor view: **current value = principal + approved profit awaiting payment**. Profit already paid out
has left the policy, so it is shown separately as "Profit received". Results not yet in an approved run
are shown as "next monthly calculation" and never counted.

## Funds view and cash guard

`/admin/funds` reads everything from the ledger. **Available = Main Operations − principal due within 30 days
(active or matured policies) − approved profit not yet paid.** A negative figure is shown in red as a shortfall.
Per policy it shows capital held (Investor Funds Held), the portion sitting in Main Operations and in the Risk Fund,
profit payable, and principal due within 30 days. Per-policy numbers use the `subject` tag on ledger lines.

Before any profit line or capital payout is marked paid, `TreasuryService::assertCanPay()` checks that Main
Operations holds at least that amount, and refuses otherwise.

## Capital payout at maturity

| Step | Who | Effect |
|---|---|---|
| Policy reaches maturity | System (daily job) | status `matured` |
| Prepare payout | Accounts | amount = principal; Directors are emailed |
| Approve (or reject) | Director, not the preparer | no cash movement |
| Mark paid with bank reference | Accounts | Dr Investor Funds Held / Cr Main Operations; policy becomes `paid_out`; investor emailed |

Only principal is returned here; profit is paid through the monthly runs. Because 5% of each subscription
moved to the Risk Fund on receipt, Main Operations only holds 95% of principal until trading proceeds return
to it. See DECISIONS.md Q12.

## Bank reconciliation

`/admin/reconciliation`: Accounts enters the closing balance from the bank statement for Main Operations,
Risk Fund, Investors Profit or Domiciliary, with the statement date. The system stores that figure beside the
ledger balance at the end of that day and the difference. Records are never edited; a correction is a new record.

## Payroll, Takaful and reports

| Event | Debit | Credit | Amount |
|---|---|---|---|
| Payroll paid | Payroll Expense | Main Operations (net) + Payroll Deductions Payable | gross; net; deductions |
| Takaful premium paid | Takaful / Insurance Expense | Investors Risk Fund | premium |

Remitting withheld deductions to the authorities is not yet posted (Q16). Payroll cannot be paid unless Main
Operations has the cash.

**Trial balance** (`/admin/reports/trial-balance`) lists every account's balance at a date; total debits must equal
total credits, which proves the double-entry ledger is intact. **Profit and loss** shows Trading Income after the
investors' approved share minus Trading, Payroll and Takaful expenses for a period.

## Trading cycle postings

Each posting is tagged with the cycle (`trade_cycle`) so it can be traced.

| Event | Debit | Credit | Amount |
|---|---|---|---|
| Purchase paid | Commodity Inventory | Main Operations | purchase total |
| Expense paid | Trading Expenses | Main Operations | expense |
| Buyer pays for a sale | Investors Profit Account | Commodity Inventory (cost of goods) + Trading Income (margin) | sale total |
| Selling below cost | Investors Profit Account + Trading Income (loss) | Commodity Inventory | |
| Cycle closes | Main Operations | Investors Profit Account | sales proceeds − 20% of net profit |

Cycle net profit = sales received − purchases paid − expenses paid. The 20% retention stays in the Investors
Profit Account; the remainder of the proceeds returns to Main Operations, which restores the capital and expenses
it paid out and adds 80% of the profit. The cycle's net profit is then shared across the allocated policies
(pro rata to allocated capital) and picked up by the monthly distribution, which pays investors 30% of the
post-retention profit.

Worked example (₦1,000,000 policy; ₦900,000 allocated): buy 10,000 kg at ₦60 = ₦600,000; haulage ₦20,000; sell
10,000 kg at ₦75 = ₦750,000. Net profit ₦130,000, retained ₦26,000. Main Operations: 950,000 − 600,000 − 20,000 +
724,000 = ₦1,054,000. Monthly run: retained 26,000, remaining 104,000, investor ₦31,200, company ₦72,800.

Note: the 20% retention is taken per cycle when it closes (cash) and again per policy per month in the
distribution run (reporting). They agree except in months where profits and losses are netted per policy.

`InvestorFundsService::settleCycle()` from milestone 1 is no longer used by the application; trading cycles
settle through `CycleService::close()`.

## Not yet implemented

- Loss treatment: a loss month distributes nothing and is not carried forward (Q9)
- Which account pays investors (Q10)
- Takaful premium postings from the Risk Fund
- Domiciliary account postings and FX
- Reconciliation from an uploaded bank statement file
- Early exit and rollover (Q6)
