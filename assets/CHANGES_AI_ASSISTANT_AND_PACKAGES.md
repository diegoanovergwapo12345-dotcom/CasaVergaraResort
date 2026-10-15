# What changed in this update

## 1. Deploy step (do this first)
Run the new migration once against your database:

```
mysql -u root casa_vergara < database/migration_packages.sql
```

This creates two new tables — `room_packages` and `event_packages` — and
seeds them with the exact same rooms/prices/add-ons that were previously
hardcoded in `js/booking-data.js`, so nothing changes for guests on day one.

No other migrations or config changes are required. Everything else below
works with tables that already existed in your database.

## 2. New files

- `database/migration_packages.sql` — creates & seeds `room_packages` and `event_packages`.
- `api/content/packages.php` — public endpoint returning active rooms/events (for future use by `pages/booking.html`; see note below).
- `api/admin/packages-list.php` — admin: list all rooms/events, including inactive.
- `api/admin/packages-save.php` — admin: create/update a room or event package.
- `api/admin/packages-delete.php` — admin: permanently delete a package (admin-only, same restriction as FAQ delete).
- `config/package_catalog.php` — static PHP fallback copy of the catalog, used only if `migration_packages.sql` hasn't been run yet.

## 3. Changed file

- `api/content/ai-assist.php` — the guest-facing AI assistant (C.A.S.A.) is now grounded in far more of the site's real content:
  - **Room & event package pricing** — reads live from `room_packages`/`event_packages` (falls back to the static copy in `config/package_catalog.php` if the migration hasn't run yet), instead of the old FAQ-only grounding.
  - Resort contact info (`resort_settings`)
  - Site amenities/highlights (pools, event spaces, security, sustainability)
  - Live guest review stats (count + average rating)
  - Terms & Conditions / Privacy Policy (summarized, not quoted at length)
  - Team page bios
  - Reasoning depth (`thinkingLevel`) bumped from `medium` to `high` so it can combine facts across all these sections — e.g. add up a package's base price plus add-ons a guest asks about.

## 4. Important: what this update does *not* change, and why

`pages/booking.html`, `js/booking-data.js`, and `js/admin-packages.js` are
**untouched**. Right now, admin package edits from Admin > Packages are
saved to the browser's `localStorage` only — never synced to a server. The
new `packages-save.php`/`packages-list.php`/`packages-delete.php` endpoints
above give you the real backend to fix that, but I deliberately did not
rewire the booking page or the admin Packages page to call them yet.

Reasoning: `js/booking-data.js` is deeply woven into the live booking flow —
pricing calculations, add-on selection, motif pickers — and your site has
real customers and real payment proofs already flowing through it. Rewiring
that blind, without a staging environment to test against, risks breaking
checkout for real guests. The safer path is:

1. Deploy this update (AI assistant + new backend) — zero risk, additive only.
2. Separately, and ideally on staging first, point `js/booking-data.js` and
   `js/admin-packages.js` at `api/content/packages.php` /
   `api/admin/packages-*.php` instead of the hardcoded objects and
   localStorage. I'm glad to do that as a focused follow-up.

Until step 2 happens, keep `config/package_catalog.php` and
`database/migration_packages.sql`'s seed data in sync by hand if you change
prices in `js/booking-data.js` — the AI assistant reads the DB tables, not
the JS file.

## 5. Security note (unrelated to this update, but worth fixing)
`config/config.php` has a live Gemini API key hardcoded directly in the
file rather than only read from an environment variable. If this codebase
is ever shared, committed to a public repo, or included in a zip like this
one, rotate that key and set it via `AI_API_KEY` in your environment
instead.
