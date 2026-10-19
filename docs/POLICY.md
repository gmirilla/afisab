# Investment agreement (policy document)

Every investment gets a **Mudharabah Investment Agreement**: a PDF with a cover, the parties and certificate number, 18 clauses, a schedule of the policy's details, and an execution page. It sits alongside the existing one-page certificate (which still carries the QR verification code).

## Life of an agreement

1. **Subscribing.** The investor chooses a plan and a profit-payment frequency and must tick *"I have read and agree to the Mudharabah Investment Agreement"*. A link opens the full wording on screen first (`/investor/agreement`), the same words as the PDF with blanks left for what is only known later. On subscribing, the system **freezes the terms** on the policy (see below) and records the time and IP address of the acceptance.
2. **Payment confirmed.** A background job issues the agreement and emails it to the investor with the PDF attached. It runs after the payment has been saved, off the web request, and retries; a PDF problem can never undo a confirmed payment. If it ever does not run, staff can press **Generate agreement** on the investor's page, and the investor can press **Prepare and email it to me**.
3. **Afterwards.** The investor downloads it or emails themselves a copy. Staff reprint, resend or re-issue.

## What is fixed at subscription

`investments.terms` holds the investor share, retention, the four profit shares, term, frequency, notice period for early withdrawal and arbitration seat. The agreement quotes these figures, and **the monthly distribution calculates with them** (policies created before this feature have no frozen terms and follow today's rules until their agreement is first issued, which freezes them). So a later change to the company's settings cannot make a contract say one thing while the system pays another.

Profit shares are shown as percentages of **net profit**, as a Mudharabah agreement states them. With the current rules:

| Clause 6.1 | Share | Where it comes from |
|---|---|---|
| Investor | 24% | 30% of what is left after the 20% retention |
| AFISAB (Mudarib) | 56% | the other 70% of what is left |
| Risk Fund | 0% | the 5% risk reserve is taken from capital when money arrives, not from profit |
| Reserve Fund | 20% | the retention |

## The stored document

Each issued agreement is saved as a file with its **SHA-256 fingerprint**. Reprints and resends return that exact file. A correction (new address, new signatory) is a **new version** that supersedes the old one, with a required reason; old versions are kept and can still be downloaded by staff. Issued records cannot be edited or deleted (the database layer refuses).

Policy numbers look like `MIC/10-2026/OYO/0000001` (month and year of the start date, the place code, a gap-free serial). The code is `AFISAB_POLICY_CODE` (built-in default `NG`; `deploy/.env.production.example` sets `OYO` as in the sample, so set whichever the company wants).

## Signature

The super-admin sets the company signatory at **Investors > Policy signatory**: name, title and a signature image (PNG or JPG up to 1 MB; it is resized and re-encoded, keeping transparency). Your password is required and every change is audited. The image is kept on the private disk, is never served publicly, and is only embedded into PDFs. Each document records which signatory it used, so replacing the signature never changes an agreement already issued. Until one is set the dashboard warns, and agreements are issued with an unsigned line.

The investor's side is signed **electronically**: the page shows "Accepted electronically on {time}" with the IP address. Witness lines appear for a printed, hand-signed copy only.

## Reprint and resend

| Who | What |
|---|---|
| Investor | Download; email themselves a copy (3 per policy per day, to their registered address only) |
| Anyone with `investors.view` | Download any version from the investor's page |
| Accounts, Compliance (`policies.send`) | Resend the current version to the investor |
| Compliance (`policies.issue`) | Generate or re-issue a new version with a reason, optionally emailing it |

Every download, email and re-issue is in the audit trail.

## Early withdrawal (clause 9.2)

The agreement lets an investor withdraw early on **one month's notice** (frozen on the policy), "subject to the availability of funds and settlement of ongoing transactions". The software does exactly that:

1. The investor gives notice on their policy page. The policy keeps earning, but its capital is no longer put into new trading cycles.
2. When the notice has run **and** none of the policy's capital is still in a pending or open trading cycle, the policy **matures early** (a daily job at 06:30, or Accounts press *Mature now* on **Early withdrawals**).
3. The ordinary maturity path takes over: the final profit run settles profit held back for the payment frequency; the capital payout is prepared by Accounts, approved by a Director and paid by Accounts, blocked by the cash check if Main Operations cannot cover it.

The investor can cancel until the policy matures. There is no fee in the template, so none is charged.

## Profit frequency

Monthly, quarterly, half-yearly, yearly, or one-off payment. See `ProfitSchedule` for when each is due.

## Changing the wording

The text is data in `PolicyDocumentService::sections()` and `schedule()`, rendered by `resources/views/pdf/policy.blade.php` (PDF) and `resources/views/investor/agreement.blade.php` (screen). Edit the wording only with counsel, and raise `PolicyTerms::TEMPLATE_VERSION`: each issued document records the version it was made from.

## Open points

Several clauses in the template describe things the software does not do (losses reducing capital, early withdrawal, terms beyond 12 months), and the Shariah wording needs confirmation. They are recorded as **Q19 to Q27 in [DECISIONS.md](DECISIONS.md)** and are printed exactly as the template has them until decided. Items in the sample's schedule that belong to a takaful savings product (interest-free loan against a "PIA" balance, contribution waiver, death cover, instalments, age next birthday) are deliberately **not** printed.
