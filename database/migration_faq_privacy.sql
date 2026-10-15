-- ============================================================
-- Migration: FAQ chatbot + professional Terms & Privacy content
-- Run this ONCE if you already imported an earlier database/schema.sql
-- (i.e. your `casa_vergara` database already exists).
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

-- 1) Allow a 'privacy' row in terms_and_conditions (previously only
--    room/event/general were allowed).
ALTER TABLE terms_and_conditions
  MODIFY COLUMN booking_type ENUM('room', 'event', 'general', 'privacy') NOT NULL DEFAULT 'general';

-- 2) Re-write the room & event terms using the "## Section Title" format
--    so they render as clean, numbered sections instead of one long block
--    of text, and add the Privacy Policy content.
--    (Safe to re-run: it deactivates old rows and inserts fresh v1.1 rows.)
UPDATE terms_and_conditions SET is_active = 0 WHERE booking_type IN ('room', 'event', 'privacy');

INSERT INTO terms_and_conditions (booking_type, version, content, is_active) VALUES
('room', '1.1', '## Quotation & Confirmation\nA valid quotation must be reviewed and approved by the client before any room reservation is considered confirmed. Reservation dates are held tentatively while a quotation is pending your approval.\n\n## Downpayment\nA downpayment of at least 50% of the quoted amount is required to secure the reservation. Reservations without a validated downpayment may be released to other guests.\n\n## Cancellations & Refunds\nCancellations made 7 days or more before the reservation date are eligible for a 50% refund of the downpayment. Cancellations made less than 7 days before the reservation date forfeit the downpayment in full.\n\n## Check-in & Check-out\nCheck-in and check-out times will be specified in your confirmed quotation. Early check-in or late check-out requests are subject to availability and may incur additional charges.\n\n## Identification\nCasa Vergara reserves the right to request valid government-issued identification upon check-in for all room bookings.', 1),
('event', '1.1', '## Quotation & Customization\nAll event customizations (theme, motif, lights, sound, and add-ons) are subject to a formal quotation and must be approved by the client before the booking is confirmed. Prices shown during customization are estimates until the quotation is issued.\n\n## Downpayment\nA downpayment of at least 50% of the quoted amount is required to reserve your event date. The date is not guaranteed until this downpayment is verified.\n\n## Final Details\nFinal guest count and customization details must be confirmed at least 5 days before the event date. Changes requested after this window may not be guaranteed and may affect pricing.\n\n## Cancellations & Refunds\nCancellations made 14 days or more before the event date are eligible for a 50% refund of the downpayment. Cancellations made less than 14 days before the event forfeit the downpayment in full.\n\n## Force Majeure\nCasa Vergara is not liable for delays or disruptions caused by circumstances beyond its reasonable control, including severe weather, government advisories, or other force majeure events.\n\n## Outside Vendors\nOutside catering, decor, or entertainment services require prior written approval from Casa Vergara management before the event date.', 1),
('privacy', '1.0', '## Information We Collect\nWhen you create an account, make a reservation, or request an event quotation, we collect information such as your name, email address, phone number, and the booking details you provide (dates, guest count, customization preferences, and uploaded proof of payment).\n\n## How We Use Your Information\nWe use your information to process reservations and quotations, verify payments, send booking confirmations and updates by email, respond to inquiries, and improve our services. We do not sell your personal information to third parties.\n\n## Payment Proof & Uploaded Files\nProof-of-payment files you upload are stored securely and are only accessible to authorized Casa Vergara staff for the purpose of validating your payment. Please avoid uploading documents containing sensitive information beyond what is needed to confirm payment.\n\n## Data Retention\nWe retain booking and account records for as long as reasonably necessary to fulfill the purposes described in this policy, meet legal obligations, and resolve disputes.\n\n## Your Choices\nYou may request access to, correction of, or deletion of your personal information by contacting us directly. Deleting your account may affect our ability to honor existing reservations.\n\n## Contact Us\nIf you have questions about this Privacy Policy or how your data is handled, please reach out through the Contact page or the details in our footer.', 1);

-- 3) Make sure the faqs table exists (no-op if you already have it from schema.sql).
CREATE TABLE IF NOT EXISTS faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(100) DEFAULT 'General',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO faqs (question, answer, category, sort_order)
SELECT * FROM (SELECT
    'How does the quotation process work?' AS question,
    'After you submit your customization request (theme, lights, sound, add-ons), our event manager reviews it and sends you a formal quotation. Your booking only proceeds once you approve that quotation.' AS answer,
    'Booking Process' AS category, 1 AS sort_order
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question = 'How does the quotation process work?');
