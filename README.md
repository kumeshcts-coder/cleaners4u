# Cleaners4u landing page

Melbourne end-of-lease cleaning landing page, quote form and thank-you page, built from the Removalists4u Melbourne page with the same design and build, rewritten for cleaning (per the "Cleaners4u landing page brief for the dev team", 10 Nov 2026).

## Pages

| File | What it is |
| --- | --- |
| `index.html` | Landing page with the step 1 quote form. `?h=moveout` / `?h=movein` switch the headline and pre-select that clean in step 2. |
| `additional-details.html` | Quote form step 2: the clean, the property, extras, then the move (10% off) and free utility cards. |
| `thank-you.html` | Thank-you page: clean at a glance, next steps, trust section, last-chance extras. |
| `bond-back-guarantee/index.html` | Bond-back guarantee terms (draft for legal review). |

Static HTML, CSS and JS only; no build step. To preview locally:

```bash
python -m http.server 8080
```

Then open http://localhost:8080. On localhost or `file:` the forms skip the server post and go straight to the next page, as on Removalists4u.

## Assets

- `assets/logo.svg`: new Cleaners4u logo (navy and mint, Barlow; a mint water drop with a sparkle stands in for the "a", with the "4U" and mint lines from the Removalists4u mark). `assets/favicon.svg` uses the same drop.
- `assets/img/`: cleaning photos (free Pexels photos), WebP.
- `assets/badges/`, `assets/partners/`: copied from Removalists4u.
- `assets/suburbs.js`: covered-suburb list for the step 2 suggestions.

## Before launch (from the brief's [bracketed] items)

- **Phone number:** all call links use the Cleaners4u number 03 7042 9778 (`tel:0370429778`). To change it, replace both forms in every `.html` file.
- **Before and after:** four side-by-side pairs of separate free Pexels photos (sink, cooktop, bathroom, shower tiles), not Cleaners4u jobs. Swap in real job photos when available (`assets/img/ba-*-before.webp` / `ba-*-after.webp`, 800×600) and put back the brief's heading "Real results from Melbourne homes."
- **Reviews:** add real Google reviews about cleaning to `assets/reviews.js`. The review cards stay hidden until it has entries (the moving reviews were removed).
- **Stats:** 15,000+ cleans completed (index.html and thank-you.html).
- **FAQ:** answers in grey italics need confirming (being home, equipment, deposit and cancellation).
- **Footer and terms page:** business name, ABN, address and email for the footer; lawyer review of the guarantee page (keep section 5 word for word).
- **Other:** accreditation badges, 10% discount terms, general-clean scope, cleaning suburbs, "On cleaning day" process, partner logo check, privacy policy link (currently the Removalists4u one).
- **Backend:** step 1 and step 2 post to `/insert.php` like Removalists4u. The new cleaning fields (listed in the comment in `additional-details.html`) need adding to the CRM; until then everything is also summarised into the `info` field. A lead that adds a move is flagged `combined_booking=1` and must reach the moving sales team.
