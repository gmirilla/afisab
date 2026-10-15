# User acceptance testing (UAT)

Run this with **real staff on the staging site using Paystack TEST keys and test bank details**, before launch. Each
scenario says who does it, what to do, and what you must see. Tick the box when it behaves as described; write
anything surprising in the notes column. Nothing here should need a developer.

Set up first: a staging copy with the release zip, seeded data, `php artisan afisab:make-staff` for the first Director,
then the Director creates one account per role at **Users** (accounts, sales, procurement, hr, compliance, auditor) and
every person sets a password and turns on two-factor authentication.

## A. Investor journey (a test investor)

| # | Do this | You must see | ✔ | Notes |
|---|---|---|---|---|
| A1 | Register with name, email, phone and a weak password | Weak password is refused; a strong one works | | |
| A2 | Click the verification link in the email | You are signed in and see "My investments" | | |
| A3 | Try to open **Invest** before KYC | You are told to complete KYC first | | |
| A4 | Fill in Profile & KYC (BVN 11 digits, bank, ID upload as `.exe`) | The `.exe` upload is refused; a JPG/PDF is accepted; status "Submitted" | | |
| A5 | (Compliance) Approve the KYC | Investor sees "Approved" | | |
| A6 | Subscribe ₦400,000 to the 6-month plan | Refused: minimum is ₦500,000 | | |
| A7 | Subscribe ₦500,000 to the 6-month plan | A pending policy appears with payment options | | |
| A8 | Pay with **Paystack** (test card) | Policy becomes Active, certificate number shown, confirmation email arrives | | |
| A9 | Subscribe again and pay by **bank transfer**: upload a receipt | "Awaiting confirmation by Accounts" | | |
| A10 | (Accounts) Confirm the transfer | Policy is Active; investor gets an email | | |
| A11 | Download the **certificate PDF** | Correct name, amount, dates; QR code; ₦ symbol prints | | |
| A12 | Scan the QR code / open `/verify` and type the code | "Valid certificate", name shown masked (A*** O***), amount and dates correct | | |
| A13 | Download the **statement PDF** | Principal, profit and payments correct | | |
| A14 | Ask a colleague (a different investor) to open your policy URL | They get "Page not found" | | |

## B. Money in and the ledger (Accounts + Director)

| # | Do this | You must see | ✔ | Notes |
|---|---|---|---|---|
| B1 | **Funds** after A8 | 5% of the policy is in the Risk Fund, 95% in Main Operations | | |
| B2 | **Reports → Trial balance** | "Debits equal credits" | | |
| B3 | **Reconciliation**: enter the bank statement balance for Main Operations | Balanced if it agrees; a difference is highlighted in red if not | | |
| B4 | Try to confirm the same transfer twice (two browser tabs) | Money is booked once | | |

## C. A trading cycle (Sales, Procurement, Accounts, Director)

| # | Who | Do this | You must see | ✔ | Notes |
|---|---|---|---|---|---|
| C1 | Procurement | Add a supplier and Sales adds a buyer | Both saved | | |
| C2 | Sales | Open a cycle (Sesame) and allocate part of a policy | Budget shows | | |
| C3 | Sales | Try to allocate more than the policy's free capital | Refused with the free amount | | |
| C4 | Procurement | Propose a purchase | "Awaiting approval" | | |
| C5 | Procurement | Try to approve their own purchase | Not allowed | | |
| C6 | Accounts | Approve, then pay (not the proposer) | Paid; cash in Main Operations drops | | |
| C7 | Director | A purchase above ₦5,000,000: Accounts tries to approve | Refused: Director needed | | |
| C8 | Procurement | Receive goods: quantity, grade, moisture, QA **failed** | Stock appears marked Failed and cannot be sold | | |
| C9 | Procurement | Receive another lot with QA **passed** | Sellable stock | | |
| C10 | Sales | Draft and confirm a sale; try to sell more than is in stock | Over-sale refused | | |
| C11 | Accounts | Record the buyer's payment | Sale paid | | |
| C12 | Sales | Close the cycle | Net profit shown; Main Operations receives proceeds less 20% | | |
| C13 | Sales | Try to close a cycle with unsold stock | Refused with the reason | | |

## D. Monthly profit (Director, Accounts) — run on or after the 27th, or calculate by hand

| # | Do this | You must see | ✔ | Notes |
|---|---|---|---|---|
| D1 | **Profit runs**: calculate the month | Net profit, 20% retained, then 30% investors / 70% company: check one policy with a calculator | | |
| D2 | The person who calculated tries to approve | Refused | | |
| D3 | A different Director approves | Run is Approved; investors can now see the profit | | |
| D4 | Download the bank CSV | Names, banks and amounts are right | | |
| D5 | Mark paid with a bank reference | Investor receives "profit paid" email; run becomes Paid | | |
| D6 | Check the investor's dashboard | Profit received and current value are right | | |
| D7 | After the 29th with an unpaid run | Dashboard shows OVERDUE and reminder emails go out | | |

## E. Maturity (Accounts, Director)

| # | Do this | You must see | ✔ | Notes |
|---|---|---|---|---|
| E1 | 14 days before maturity | Investor gets a reminder email | | |
| E2 | After maturity | Policy shows Matured | | |
| E3 | Try to pay out principal while the capital is still in an open cycle | Refused: close the cycle first | | |
| E4 | Prepare, approve (different person), pay | Policy is Paid out; investor emailed; cash guard blocks if Main Operations is short | | |

## F. Staff, risk and HR

| # | Do this | You must see | ✔ | Notes |
|---|---|---|---|---|
| F1 | Director creates a staff user | The person gets a set-password email and is forced to set up 2FA at first sign-in | | |
| F2 | Director disables a user who is signed in | They are signed out on their next click and cannot sign in again | | |
| F3 | Try to disable your own account / the last Director | Refused | | |
| F4 | **Risk**: switch on a supplier limit lower than a pending purchase, then approve it | Refused with the figures | | |
| F5 | Record a Takaful policy and pay the premium | Risk Fund reduces by the premium; shows on the Risk page | | |
| F6 | HR adds staff, allowances and a deduction, prepares payroll | Totals are right | | |
| F7 | HR tries to approve their own payroll; Director approves; Accounts pays | Segregation enforced; ledger shows payroll expense | | |
| F8 | Auditor opens Reports, Audit trail, Funds | Can see everything, change nothing | | |

## G. Security spot-checks (a tester with no login, then an investor login)

| # | Do this | You must see | ✔ | Notes |
|---|---|---|---|---|
| G1 | Type `/admin` while signed out | Sent to the sign-in page | | |
| G2 | As an investor, type `/admin/funds` | "Access denied" | | |
| G3 | Enter a wrong password six times | Locked out for a minute | | |
| G4 | Sign in as staff without 2FA | Held on the security page | | |
| G5 | Type `/storage/kyc/anything.jpg` | "Page not found" | | |
| G6 | Run `php artisan afisab:preflight` on the server | 0 failures | | |

## Sign-off

| Role | Name | Signature | Date |
|---|---|---|---|
| Managing Director | | | |
| Head of Accounts | | | |
| Compliance officer | | | |
| IT / system owner | | | |

All scenarios must pass, or have an agreed, written workaround, before launch.
