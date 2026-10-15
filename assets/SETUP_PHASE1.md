# Casa Vergara — Backend Setup Guide (Phase 1)

This covers everything needed to run **Phase 1: Database + Login/Register with Email Verification** on your XAMPP environment.

## What's included in this phase
- Full MySQL database schema (`database/schema.sql`) — includes tables for later phases too (bookings, quotations, payments, FAQs, T&Cs) so nothing will need to be rebuilt as we add features.
- Working registration, email verification, login, logout, and session-check API (`api/auth/*.php`).
- Real emails sent through Gmail SMTP using PHPMailer (already included in `includes/PHPMailer/` — no Composer needed).
- The Register and Login pages now talk to this real backend instead of browser localStorage.
- A default admin/event-manager account is seeded for you to log into the admin side later.

## 1. Install the project into XAMPP
1. Copy this whole `resort` folder into `C:\xampp\htdocs\` (Windows) or `/Applications/XAMPP/htdocs/` (Mac), so the path is `htdocs/resort/...`.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.

## 2. Import the database
1. Open `http://localhost/phpmyadmin`.
2. Click **Import** → choose `database/schema.sql` → **Go**.
3. This creates the `casa_vergara` database with all tables and starter data (motifs, add-ons, FAQs, T&Cs, pricing rules). Run the migration scripts below to seed the real admin and staff logins.

## 3. Configure the database connection
Open `config/database.php`. The defaults already match a stock XAMPP install:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'casa_vergara');
define('DB_USER', 'root');
define('DB_PASS', '');
```
Only change `DB_PASS` if you set a MySQL root password yourself.

## 4. Configure the site URL
Open `config/config.php` and confirm this matches your folder name:
```php
define('SITE_URL', 'http://localhost/resort');
```
If you renamed the project folder to something other than `resort`, update this to match (e.g. `http://localhost/casa-vergara`).

## 5. Set up Gmail for sending real verification emails
The site uses your Gmail account to send emails — **not** a fake SMS, but real email delivery via Gmail's SMTP server.

1. Use (or create) a Gmail account for the resort.
2. Turn on 2-Step Verification: https://myaccount.google.com/security
3. Create an App Password: https://myaccount.google.com/apppasswords
   - App: **Mail**, Device: **Other** → name it "Casa Vergara Website"
   - Google gives you a 16-character password (e.g. `abcd efgh ijkl mnop`)
4. Open `config/mail.php` and fill in:
```php
define('GMAIL_ADDRESS', 'yourresort@gmail.com');
define('GMAIL_APP_PASSWORD', 'abcd efgh ijkl mnop');
```
**Important:** Use the 16-character App Password, not your normal Gmail login password — Gmail will reject the normal password for SMTP.

## 6. Test it
1. Visit `http://localhost/resort/pages/register.html` and create an account.
2. Check the inbox of the email you registered with — you should receive a "Verify your Casa Vergara account" email within a few seconds.
3. Click the verification link — it will redirect you to the login page with a success message.
4. Log in with that account.

## Default admin/event-manager login
The old placeholder account (`admin@casavergara.test` / `Admin@123`) has been removed. Real logins are seeded by two separate migration scripts — after importing `schema.sql`, open **phpMyAdmin → casa_vergara → SQL tab**, paste in each file's contents, and click **Go**:

- `database/migration_official_admin.sql` — full admin login (`casavergararesort@gmail.com`)
- `database/migration_official_staff.sql` — event-manager/staff login (`louisediego.anover@cvsu.edu.ph`)

Both are safe to re-run at any time; re-running just resets the password/role instead of creating a duplicate account.

## Shared reservation calendar + booking notifications
Run `database/migration_reservations.sql` the same way (phpMyAdmin → SQL tab → paste → Go). This adds a `reservations` table that mirrors every booking submitted through the site, so:
- The check-in/check-out and event-date calendars (reservation form, edit/reschedule flows) show dates already taken by **any** guest or by admin — not just the current browser.
- Admin/staff get a bell notification the instant a guest submits a new booking.

This is safe to re-run and does not touch existing data in any other table.

## Live chat (staff/admin)
Run `database/migration_live_chat.sql` the same way, or skip it — the chat APIs create the tables automatically on first use. Guests use **Chat with Resort Staff**; staff and admins reply from **Admin > Live Chat**. Replies come only from logged-in staff/admin accounts.

**Phase 2** additionally makes admin's Reservations page and a guest's own Dashboard/status page read from the server too (not just the current browser):
- Any status/payment/quotation change admin makes on `pages/admin/reservations.html` (and the payment-verification table) now syncs to the server — including cancelling a booking, which now actually frees the date up for other guests.
- Admin's reservations list pulls every booking from the server on load, so a booking made on a guest's phone shows up in the Admin Portal immediately, not only if admin happens to use the same browser.
- A logged-in guest's Dashboard and reservation-status page pull their own bookings from the server too, so switching devices/browsers doesn't "lose" a reservation.

No extra migration needed for Phase 2 — it uses the same `reservations` table from Phase 1.

## Troubleshooting
- **Email not sending?** Visit `http://localhost/resorts/api/auth/test-mail.php?to=your@email.com` in your browser (replace with your folder name and a real recipient email). It sends one real test email and prints the exact success or error message on screen within ~12 seconds — no DevTools or log-hunting needed. **Delete this file before the site ever goes anywhere public.**
- **"Database connection failed"** → Make sure MySQL is running in XAMPP and you imported `database/schema.sql`.
- **"This site can't be reached" when clicking the email link** → Confirm `SITE_URL` in `config/config.php` matches the URL you use to access the site.
- **Blank page after login** → Make sure `pages/customer-dashboard.html` exists and Apache is serving the `pages/` folder correctly (it should, no extra config needed).

## What's next (upcoming phases)
1. ~~Database + backend core + email verification~~ ✅ done
2. Custom package builder — remove fixed packages, add live customization pricing (theme, motif, lights, sound)
3. Quotation & approval workflow (admin sends quote → client approves before booking confirms)
4. Payment process with proof-of-payment upload + admin validation
5. FAQs (replacing the old chat widget, not in the navbar)
6. Terms & Conditions (professional version, embedded in booking/event pages, not in navbar)
7. Admin: Event Manager tools for reviewing customizations, quotations, approvals, schedules, payments
8. Calendar-based scheduling
