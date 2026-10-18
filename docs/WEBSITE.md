# Public website

The marketing site is part of the same application. Visitors see it at `/`; investors and staff sign in at `/login` and see the application layout.

## Pages

| URL | Purpose |
|---|---|
| `/` | Home: slogan, what we do, commodities, why AFISAB, buyers and suppliers, markets |
| `/what-we-trade` | The active commodities, grouped (live from the database, so it follows operations) |
| `/for-buyers` | What buyers can expect; **quote request form** |
| `/for-suppliers` | Who we buy from; **supplier interest form** |
| `/how-we-work` | Source, aggregate, quality-check, store, supply; the controls we work under |
| `/about` | Company details, commitments, markets |
| `/contact` | Phone, email, WhatsApp; **general message form** |
| `/privacy`, `/terms`, `/risk-notice` | Legal pages (general templates: **have counsel review them**) |
| `/for-investors` | **Off by default.** See below |
| `/sitemap.xml`, `/robots.txt` | For search engines (robots keeps `/admin`, `/investor`, `/account`, `/dashboard` out) |

## Rules this site follows

- **Only confirmed facts.** The brief confirmed: name, industry, customers, suppliers, markets (Nigeria domestic, international as an expansion opportunity), slogan, email and phone. Beyond those, the pages describe controls the software really enforces (quality check and recording of moisture and grade, traceable lots, separate approval and payment of purchases, an unalterable ledger). `PublicSiteTest` fails if words like "certified", "nationwide" or "years of experience" appear.
- **The investment programme is not promoted.** Promoting an investment scheme to the public needs regulatory clearance (DECISIONS.md Q5). While `AFISAB_PUBLIC_INVESTOR_PAGE` is false, `/for-investors` is a 404, the sitemap omits it, and the only mention anywhere is a plain "Investor portal" sign-in link.
- **Forms cannot be abused to email strangers.** Nobody who submits a form is emailed back; the shared mailbox is notified instead.

## Settings (`.env`)

| Setting | Default | Meaning |
|---|---|---|
| `AFISAB_CONTACT_EMAIL` | info@afisab.com | Shown on the site |
| `AFISAB_CONTACT_PHONE` | +2348057520669 | Shown on the site; also used for the WhatsApp link |
| `AFISAB_ENQUIRY_EMAIL` | info@afisab.com | Where every form submission is emailed |
| `AFISAB_PUBLIC_INVESTOR_PAGE` | false | Publishes `/for-investors` |
| `AFISAB_COMPANY_NAME` | AFISAB NIG. LTD | Name in the header, footer, emails and structured data |

## Enquiries

Every submission is stored and emailed. Sales and Directors work them at **Website enquiries** in the back office (status, internal note; supplier interest becomes a supplier record with one click). New ones show in the dashboard "Waiting for you" list.

Defences on the public forms: consent tick box (recorded with a timestamp), a hidden field that only scripts fill in, a minimum time to fill in the form (4 seconds) and a maximum (2 hours), a limit of 6 submissions per visitor per 10 minutes, and strict validation. Messages are stored as plain text and shown escaped.

## Content still needed from the company

The site deliberately has no placeholders on screen. Add these as you confirm them (tell the developer, or edit the files in `resources/views/public/`):

- **Photographs**: produce, warehouses, loading, the team
- Registered **address**, **RC number**, year founded
- Warehouse locations, typical volumes, certifications or standards, named customers or testimonials (only if they have agreed)
- Names and roles of the leadership, if you want a team section
- A social media or LinkedIn link, if used

## The logo

The company logo is `public/image/logo.png` (the original, with a transparent background). Three smaller copies are generated from it and are what the pages actually use:

| File | Used for |
|---|---|
| `public/image/logo-mark.png` | The symbol only, square: the navbar and footer of both the public site and the application (shown on a white tile, because the logo's dark green would vanish on the green header) |
| `public/image/logo-full.jpg` | The full logo with the "AFISAB NIG. LTD" wordmark, flattened onto white: the certificate and statement PDFs (embedded in the file, so PDFs work wherever the web root sits) |
| `public/image/favicon.png` | The browser tab icon |

To change the logo, replace `logo.png` and regenerate the three copies (crop the symbol to a square for the mark, flatten to a JPEG on white for the PDFs). If `logo-full.jpg` is missing, the PDFs fall back to the company name in text.

## Changing the text

The pages are plain Blade templates in `resources/views/public/` (`home`, `trade`, `buyers`, `suppliers`, `how`, `about`, `contact`, `privacy`, `terms`, `risk`, `investors`). The commodity list is the only database-driven part.
