# Casa Vergara — Security Review

Reviewed: 17 September 2026. Covers `api/`, `includes/`, `config/`, the
upload folders, and the client-side auth flow in `js/main.js`.

This is a code read, not a penetration test. Nothing here is a claim that
the system is or isn't safe to deploy — it's a list of what's already solid
and what to fix, roughly in priority order.

---

## What's already done well

- **SQL injection** — every query goes through PDO prepared statements with
  bound parameters, and `PDO::ATTR_EMULATE_PREPARES` is off, so placeholders
  are handled by MySQL rather than string-substituted. The only query with an
  interpolated table name (`api/admin/packages-delete.php`) picks that name
  from a fixed whitelist, so it can't be steered from the request body.
- **Passwords** — `password_hash()` / `password_verify()` with the default
  algorithm. No plaintext or reversible storage anywhere, and no password is
  ever written to `localStorage`.
- **Authorization** — every file in `api/admin/` calls `require_role()`, and
  `require_login()` re-reads the account's `status` on each protected request,
  so suspending someone kills their existing session rather than waiting for
  it to expire.
- **Brute force** — login lockout after 5 consecutive failures
  (`migration_login_lockout.sql`) and a separate 24-hour block for repeated
  wrong password-reset codes.
- **Account enumeration** — `forgot-password.php` and `resend-verification.php`
  return the same message whether or not the email exists.
- **Uploads** — `api/admin/upload-avatar.php` verifies the file really is an
  image with `getimagesize()` rather than trusting the browser's MIME type;
  the guest receipt upload rejects executable extensions anywhere in the
  filename, which blocks `receipt.php.jpg` tricks; filenames are regenerated
  with random bytes rather than reusing what the user submitted.
- **Sensitive folders** — `config/`, `database/` and `includes/` each carry an
  `.htaccess` denying direct web access.
- **Session cookie** — `httponly` and `samesite=Lax`, and `session_regenerate_id(true)`
  runs on login, which defeats session fixation.

---

## Findings

### 1. Mail diagnostic was reachable by anyone — *fixed in this pass*
`api/auth/test-mail.php` would send a real email to any address given in the
URL and print the SMTP error text on screen. On a public host that's a free
mail relay plus an information leak. It now refuses any request that doesn't
come from `127.0.0.1`. **Delete the file entirely before going live** — the
guard is a safety net, not a reason to keep it.

### 2. Gmail app password is committed in the source — *action needed*
`config/mail.php` contains a real Gmail address and a 16-character app
password in plaintext. The `.htaccess` stops Apache from serving that folder,
but it does nothing about the file being copied, zipped, emailed or pushed to
GitHub. **Revoke that app password and issue a new one**, and keep the new
one out of anything you share. Same thought applies to `DB_USER = root` with
an empty password — fine for XAMPP, never for a real server.

### 3. Uploaded receipts are publicly readable — *partly mitigated in this pass*
`uploads/proofs/` had no `.htaccess`, and `uploads/` itself had none. Both now
exist, blocking script execution and directory listing. What remains: anyone
who knows (or guesses) a receipt's URL can open it without signing in, and
receipts contain names, amounts and reference numbers. The filenames include
12 random hex characters, so guessing is impractical, but for real deployment
serve these through a PHP script that checks `require_role(['admin', ...])`
instead of letting Apache serve the folder directly.

### 4. No CSRF tokens anywhere — *low risk here, worth knowing*
No endpoint carries a CSRF token. What's saving you is that the session cookie
is `SameSite=Lax`, which stops the browser from attaching it to cross-site
POST requests, and every state-changing endpoint requires POST with a JSON
body. That covers the classic attack. If you ever relax the cookie to
`SameSite=None`, or add a state-changing GET endpoint, this becomes a real
hole. A per-session token checked on every POST is the proper fix.

### 5. Staff permissions differ between the UI and the server
`api/admin/faqs-save.php` accepts `event_manager`, and the AI Assistant page
lets staff add and edit FAQs — but the FAQ Manager page hides those controls
from staff. So the read-only staff view is a *UI* restriction, not a security
boundary: a staff account could still POST directly to the endpoint. That may
well be what you intended (staff edit from one page, not the other), but it's
worth being deliberate about. If staff should genuinely not edit FAQs, change
`require_role(['admin', 'event_manager'])` to `require_role(['admin'])` in
`faqs-save.php`. The same pattern is worth checking wherever
`.admin-only-action` hides a button.

### 6. Server error details are passed back to the browser
Several endpoints return `mailError` in the JSON response (see
`register.php`). That text can include SMTP hostnames and failure reasons.
Useful while building; log it with `error_log()` instead once the site is
live.

### 7. Production hardening still to do
- `session.cookie_secure` isn't set in `config/config.php` — turn it on the
  moment the site is served over HTTPS, otherwise the session cookie travels
  in the clear.
- `SITE_URL` is hardcoded to `http://localhost/resorts`; verification and
  reset emails will point at localhost until it's changed.
- `CHECKIN_REMINDER_SECRET` and `CHECKOUT_REMINDER_SECRET` are still the
  shipped `change-me-...` defaults. Anyone who reads the source can trigger
  those cron endpoints over HTTP.

---

## Notes on the "remember me" feature added this session

Worth recording, since auto-login is the part of a system most likely to be
questioned:

- Clicking a saved account does **not** authenticate on an email address.
  The proof is a random selector + validator pair that only a correct
  password sign-in can mint.
- The browser holds both halves in an `httpOnly`, `SameSite=Lax` cookie, so
  page JavaScript, browser extensions and any XSS on the site cannot read it.
- The database stores the selector plus a SHA-256 hash of the validator, so a
  dumped `remember_tokens` table can't be replayed as a login.
- The validator is rotated on every use, so a copied cookie stops working as
  soon as the real browser signs in again.
- Comparison uses `hash_equals()`, which takes the same time regardless of
  how much of the value matched, so it can't be guessed a byte at a time.
- Tokens expire after 30 days (`REMEMBER_ME_DAYS`), and suspended or
  unverified accounts are dropped from the picker.

The honest caveat: anyone with physical access to an unlocked browser where an
account was saved can open the site and be signed in. That is the inherent
trade-off of the feature — it's how Facebook's own picker behaves — and the
per-account × removes the token when a device shouldn't keep it.
