-- ============================================================
-- Casa Vergara Resort - Database Schema
-- Import this into phpMyAdmin (XAMPP) to create the database.
--
-- This is the CORE schema only: users, FAQs, reviews, terms &
-- conditions, notifications, and resort settings. Every other table
-- the app actually uses today (reservations, room_packages,
-- event_packages, contact_messages, team_members, and the various
-- per-feature columns added later) is created by the migration_*.sql
-- files in this folder — run them after importing this file. See
-- SETUP_PHASE1.md for the full, ordered list.
--
-- (Cleaned up 2026-08-31: this file used to also create motifs,
-- customization_options, pricing_rules, bookings, booking_options,
-- quotations, payments, and ai_assistant_settings — an early pricing
-- model that was abandoned in favor of the fixed room/event packages
-- system. Nothing in the app reads those tables anymore, so they were
-- removed here. If your existing database still has them and they're
-- empty, they're safe to drop by hand; nothing depends on them.)
-- ============================================================

CREATE DATABASE IF NOT EXISTS casa_vergara CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE casa_vergara;

-- ------------------------------------------------------------
-- USERS (customers + admins/event managers)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    phone VARCHAR(30) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) DEFAULT NULL,
    role ENUM('customer', 'admin', 'event_manager') NOT NULL DEFAULT 'customer',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verification_token VARCHAR(255) DEFAULT NULL,
    verification_expires DATETIME DEFAULT NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- FAQs (replaces the old live-chat widget)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(100) DEFAULT 'General',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- REVIEWS (guest-submitted, shown on the homepage in place of the
-- old hardcoded testimonials). Guests may post more than one review
-- (e.g. after multiple separate stays); admin can hide or delete any
-- individual review from Admin > Reviews without removing the
-- guest's account.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rating DECIMAL(2,1) NOT NULL,
    comment TEXT NOT NULL,
    location VARCHAR(100) DEFAULT NULL,
    status ENUM('visible', 'hidden') NOT NULL DEFAULT 'visible',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_review_user (user_id),
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 0.5 AND 5),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TERMS & CONDITIONS (versioned, shown inline on booking pages)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS terms_and_conditions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_type ENUM('room', 'event', 'general', 'privacy') NOT NULL DEFAULT 'general',
    version VARCHAR(20) NOT NULL DEFAULT '1.0',
    content LONGTEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- NOTIFICATIONS (used for nav bell + email trigger log)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    audience ENUM('customer', 'staff') NOT NULL DEFAULT 'customer',
    booking_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    message VARCHAR(255) NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- RESORT SETTINGS (single row: resort name / contact info shown
-- in Admin > Settings, editable there)
-- ------------------------------------------------------------

-- Admin-managed photos on public pages (Admin > Site Content). Each row is
-- one named "slot" (see config/site_images.php for the list); a slot with
-- no row here falls back to the site's original default image.
CREATE TABLE IF NOT EXISTS site_images (
    slot_key VARCHAR(64) PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resort_settings (
    id INT PRIMARY KEY DEFAULT 1,
    resort_name VARCHAR(150) NOT NULL DEFAULT 'Casa Vergara Resort',
    email VARCHAR(191) NOT NULL DEFAULT 'info@casavergara.com',
    phone VARCHAR(30) NOT NULL DEFAULT '+1 (555) 123-4567',
    address VARCHAR(255) NOT NULL DEFAULT '123 Paradise Boulevard, Tropical Island, TI 12345',
    -- Payment QR code uploaded by the admin (Admin > Settings), shown on
    -- pages/payment.html. See database/migration_payment_qr.sql.
    payment_qr_path VARCHAR(255) NULL,
    payment_qr_label VARCHAR(150) NOT NULL DEFAULT '',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT resort_settings_single_row CHECK (id = 1)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT INTO resort_settings (id, resort_name, email, phone, address)
VALUES (1, 'Casa Vergara Resort', 'info@casavergara.com', '+1 (555) 123-4567', '123 Paradise Boulevard, Tropical Island, TI 12345')
ON DUPLICATE KEY UPDATE id = id;

-- Default admin / event manager account: intentionally removed.
-- Run migration_official_admin.sql for the real admin login
-- (casavergararesort@gmail.com) and migration_official_staff.sql for
-- the real event-manager/staff login (louisediego.anover@cvsu.edu.ph)
-- after importing this schema.

INSERT INTO faqs (question, answer, category, sort_order) VALUES
('How does the quotation process work?', 'After you submit your customization request (theme, lights, sound, add-ons), our event manager reviews it and sends you a formal quotation. Your booking only proceeds once you approve that quotation.', 'Booking Process', 1),
('Can I customize the price of my package?', 'Yes. We no longer offer fixed packages. Every price is computed from your chosen venue/room, guest count, motif, and selected add-ons (lights, sound, extras), then confirmed in your quotation.', 'Pricing', 2),
('What payment methods are accepted?', 'We accept GCash, bank transfer, and cash. A downpayment is required to confirm your reservation; proof of payment is uploaded and validated by our staff.', 'Payments', 3),
('How do I know my payment was received?', 'Once you upload proof of payment, our staff validates it against your quoted amount. You will see the status update to "Validated" in your dashboard and receive an email confirmation.', 'Payments', 4),
('Can I change my theme or motif after booking?', 'Changes are possible before the quotation is approved. Once approved, changes may require a revised quotation.', 'Customization', 5),
('What happens if I decline the quotation?', 'You may request revisions or cancel the request entirely. No payment is required unless you approve a quotation.', 'Booking Process', 6);

INSERT INTO terms_and_conditions (booking_type, version, content, is_active) VALUES
('room', '1.1', '## Quotation & Confirmation\nA valid quotation must be reviewed and approved by the client before any room reservation is considered confirmed. Reservation dates are held tentatively while a quotation is pending your approval.\n\n## Downpayment\nA downpayment of at least 50% of the quoted amount is required to secure the reservation. Reservations without a validated downpayment may be released to other guests.\n\n## Cancellations & Refunds\nCancellations made 7 days or more before the reservation date are eligible for a 50% refund of the downpayment. Cancellations made less than 7 days before the reservation date forfeit the downpayment in full.\n\n## Check-in & Check-out\nCheck-in and check-out times will be specified in your confirmed quotation. Early check-in or late check-out requests are subject to availability and may incur additional charges.', 1),
('event', '1.0', '## Quotation & Customization\nAll event customizations (theme, motif, lights, sound, and add-ons) are subject to a formal quotation and must be approved by the client before the booking is confirmed. Prices shown during customization are estimates until the quotation is issued.\n\n## Downpayment\nA downpayment of at least 50% of the quoted amount is required to reserve your event date. The date is not guaranteed until this downpayment is verified.\n\n## Final Details\nFinal guest count and customization details must be confirmed at least 5 days before the event date. Changes requested after this window may not be guaranteed and may affect pricing.\n\n## Cancellations & Refunds\nCancellations made 14 days or more before the event date are eligible for a 50% refund of the downpayment. Cancellations made less than 14 days before the event forfeit the downpayment in full.\n\n## Force Majeure\nCasa Vergara is not liable for delays or disruptions caused by circumstances beyond its reasonable control, including severe weather, government advisories, or other force majeure events.\n\n## Outside Vendors\nOutside catering, decor, or entertainment services require prior written approval from Casa Vergara management before the event date.', 1),
('privacy', '1.0', '## Information We Collect\nWhen you create an account, make a reservation, or request an event quotation, we collect information such as your name, email address, phone number, and the booking details you provide (dates, guest count, customization preferences, and uploaded proof of payment).\n\n## How We Use Your Information\nWe use your information to process reservations and quotations, verify payments, send booking confirmations and updates by email, respond to inquiries, and improve our services. We do not sell your personal information to third parties.\n\n## Payment Proof & Uploaded Files\nProof-of-payment files you upload are stored securely and are only accessible to authorized Casa Vergara staff for the purpose of validating your payment. Please avoid uploading documents containing sensitive information beyond what is needed to confirm payment.\n\n## Data Retention\nWe retain booking and account records for as long as reasonably necessary to fulfill the purposes described in this policy, meet legal obligations, and resolve disputes.\n\n## Your Choices\nYou may request access to, correction of, or deletion of your personal information by contacting us directly. Deleting your account may affect our ability to honor existing reservations.\n\n## Contact Us\nIf you have questions about this Privacy Policy or how your data is handled, please reach out through the Contact page or the details in our footer.', 1);
