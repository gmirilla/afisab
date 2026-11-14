# Public website

The marketing site is part of the same application. Visitors see it at `/`; investors and staff sign in at `/login` and see the application layout.

## Pages

The public site follows the web designer's design (handoff received 2026-11-14): their stylesheets, scripts, fonts and photographs live in `public/site/`, and their HTML was rebuilt as Blade (`resources/views/components/site-layout.blade.php` is the header and footer; `resources/views/public/` the pages). Our logo is used in place of theirs.

| URL | Purpose |
|---|---|
| `/` | Home: hero carousel, who we are, six featured commodities, capabilities, why AFISAB, process, buyers, suppliers, vision, contact |
| `/commodities` | The catalogue: search, filter by category, sort (the designer's script); the active commodities, live from the Commodities list staff manage |
| `/commodities/{slug}` | One page per commodity (20): photo, summary, availability on request, quality, applications, packaging, sourcing, related, quote and WhatsApp links |
| `/for-buyers` | **Quote request form**: buyer details, up to 5 commodities with quantity and unit, delivery destination and date, grade, packaging, quality requirements |
| `/for-suppliers` | **Supplier interest form**: supplier details, up to 5 commodities with quantity, location, harvest or readiness status, available-from date |
| `/contact` | **General message form**, with the contact details |
| `/privacy`, `/terms`, `/risk-notice` | Legal pages (general templates: **have counsel review them**) |
| `/for-investors` | **Off by default.** See below |
| `/about`, `/how-we-work`, `/what-we-trade` | Permanent redirects to the home page sections and the catalogue (the old addresses keep working) |
| `/sitemap.xml`, `/robots.txt` | For search engines (robots keeps `/admin`, `/investor`, `/account`, `/dashboard` and `/health` out) |

The "Investor & Staff Sign In" link goes to our real sign-in page (`/login`), which uses the designer's investor-portal layout but is headed for both investors and staff. The designer's disabled preview form is not used.

### Where things are

- **Copy and photographs per commodity:** `config/site_commodities.php` (the designer's text, keyed by slug, in catalogue order; `featured` lists the six on the home page). A commodity appears only while it is **active** in the staff Commodities list (matched on `db_name`); switching it off removes its catalogue card, page, footer link and sitemap entry. The text makes no claim about stock, grades or origin.
- **Look:** `public/site/css/` (the designer's `system.css`, `pages.css`, `trade.css`; our small `additions.css` for the multi-commodity form and notice pages), `public/site/js/` (their `navigation.js`, `hero-carousel.js`, `catalogue.js`; our `enquiry-form.js`), `public/site/assets/` (fonts, photographs, field-lines artwork). All scripts are external files, so the no-inline-script rule holds.
- **Contact details:** `App\Support\SiteContact`: email and first phone from settings; a second phone and the office address appear only once the super-admin has entered them (Signatory, stamp and address page); until then the location reads "Nigeria".
- **Photographs** are AI-generated illustrations, captioned "Illustrative" on the site so visitors are not led to think they show AFISAB's own stock, staff or premises. The designer confirmed on 2026-11-14 that all of them are AI-generated (their `ASSETS.md` records the prompts and says no third-party photo licence applies). Replace them with real photographs when AFISAB has them, and drop the "Illustrative" captions only for photographs that really show AFISAB's operations.

### Wording changed from the designer's copy

Their text claimed things the company has not confirmed, so these were softened: "Nigerian-owned" became "a Nigerian agricultural commodity trading and supply company"; "buyers at home and abroad" and "buyers in Nigeria and international markets" became buyers in Nigeria with international markets "as a future opportunity"; the "Export facilitation" capability became "Buyer support"; "Africa's most trusted agricultural commodity trading and investment company" became "a trusted partner in Nigerian agricultural commodity trade". `SiteRedesignTest` fails if the old wording returns.

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
