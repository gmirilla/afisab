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
- **Forms cannot be abused to email strangers.** The shared mailbox is notified of every enquiry. The person who wrote in (if they gave an email) gets one short fixed acknowledgement, with nothing they typed in it, at most once an hour per address.

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

## Proposed: redesign to match the designer's site (not started)

**Status: proposed, waiting for the client's web designer.** Recorded 2026-11-07. Nothing has been built.

**Request.** The client's web designer asked that the marketing site replicate https://afisab.netlify.app/, without that site's logo (ours stays).

**What that site has** (read as text and links only, so colours, fonts, spacing and the look of the images are not known):

- One long page: top bar (email and phone); header with dropdown menus (About, Commodities, What We Do, Work With Us, Contact, a Request a Quote button); hero "From Nigerian soil to global markets"; Who we are; six commodity cards (Soybeans, Sesame Seeds, Cashew Nuts, Maize, Ginger, Hibiscus Flower); six capabilities (sourcing, aggregation and procurement, quality assurance, warehousing and storage, logistics coordination, export facilitation); Why AFISAB; a six-step process (sourcing, aggregation, inspection and grading, storage and packaging, logistics coordination, buyer delivery); blocks for commercial buyers, farmers and aggregators, and the company's vision; contact; footer.
- Other pages: `/commodities` with a page per commodity, `/quote`, `/suppliers`, `/site-notices`. Nine photos (hero, inspection, warehouse, six commodities); two are captioned "Illustrative".

**Plan** (about four days of work once the inputs arrive):

1. Design tokens and layout: match their colours, fonts and spacing; top bar, header with dropdowns, skip link, footer, back to top. Our logo stays.
2. Home page rebuilt as their single page, each section a reusable component. The six commodity cards come from our commodity list (six marked as featured).
3. Their links map onto ours: Request a Quote to `/for-buyers` (multi-commodity form, `?commodity=` pre-fill), Become a Supply Partner to `/for-suppliers`, contact to `/contact`, Privacy and Terms to our existing pages. Kept: our server-side enquiry forms (not their email links), the "Investor portal" sign-in link, "Verify a certificate", sitemap, robots.
4. Commodity catalogue and a short page per commodity (their description and an Enquire button; no invented specifications).
5. Copy review against the rules above, then tests (pages, links, responsive, SEO, the no-inline-script rule, `PublicSiteTest`'s banned words).

**Claims to confirm before launch** (their copy; this site only states confirmed facts): "Nigerian-owned"; "Africa's most trusted agricultural commodity trading and investment company" (an ambition, not a fact); warehousing and storage; export facilitation and international markets; inspection and grading; images captioned "Illustrative". Until confirmed, soften or leave them out.

**Needed from the designer:** the site's source files (HTML, CSS, fonts, images) and the licence or ownership of the images.

**Open decisions, with defaults:**

| # | Decision | Default |
|---|---|---|
| 1 | Designer's source files available? | Yes; wait for them before final styling |
| 2 | Which claims above are confirmed? | Soften the unconfirmed ones |
| 3 | Per-commodity detail pages | Build the catalogue and short pages, no invented specifications |
| 4 | WhatsApp number +234 805 752 0669 confirmed? | Yes (it is the number already shown) |
| 5 | Single long home page, or keep separate About, How we work and Investors pages? | Follow their long page; keep the old pages reachable |
