-- ------------------------------------------------------------
-- Publish an updated "Room Bookings" Terms & Conditions that drops the
-- "Identification" clause (no longer requesting valid government-issued
-- ID at check-in). This follows the same versioning pattern used by
-- api/admin/legal-save.php: the previous active row is deactivated
-- (kept for history) and the new content is inserted as the active
-- version, so pages/terms.html immediately shows the updated text.
--
-- Safe to run on an already-deployed database. Run once:
--   mysql -u root casa_vergara < database/migration_legal_remove_govt_id.sql
-- ------------------------------------------------------------

UPDATE terms_and_conditions
SET is_active = 0
WHERE booking_type = 'room' AND is_active = 1;

INSERT INTO terms_and_conditions (booking_type, version, content, is_active) VALUES
('room', '1.1', '## Quotation & Confirmation\nA valid quotation must be reviewed and approved by the client before any room reservation is considered confirmed. Reservation dates are held tentatively while a quotation is pending your approval.\n\n## Downpayment\nA downpayment of at least 50% of the quoted amount is required to secure the reservation. Reservations without a validated downpayment may be released to other guests.\n\n## Cancellations & Refunds\nCancellations made 7 days or more before the reservation date are eligible for a 50% refund of the downpayment. Cancellations made less than 7 days before the reservation date forfeit the downpayment in full.\n\n## Check-in & Check-out\nCheck-in and check-out times will be specified in your confirmed quotation. Early check-in or late check-out requests are subject to availability and may incur additional charges.', 1);

-- Let every registered guest know the Room Bookings terms changed, the
-- same way a publish from Admin > Legal Content does.
INSERT INTO notifications (user_id, audience, title, message, link)
SELECT id, 'customer', 'Terms & Conditions — Room Bookings updated',
       'We\'ve published v1.1 of our Terms & Conditions — Room Bookings. Please review the changes.',
       'terms.html?type=room'
FROM users WHERE role = 'customer';
