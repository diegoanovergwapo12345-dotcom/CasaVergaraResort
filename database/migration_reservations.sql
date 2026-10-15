-- ============================================================
-- Migration: server-side reservations table
--
-- Reservations (room stays and events) were previously stored only in
-- each guest's own browser (localStorage `cvrReservations`), which is
-- why occupied dates never appeared for anyone other than the person
-- who made the booking. This table mirrors that same record shape on
-- the server so:
--   1. Every guest's availability calendar (js/reservation-calendar.js)
--      can see dates other guests (or admin) have already taken.
--   2. Admin/staff get a bell notification the moment a guest submits
--      or changes a booking (see api/content/reservations-sync.php).
--
-- The full client-built reservation object (quotation lines, add-ons,
-- motif, etc.) is kept as JSON in `payload_json` so nothing about the
-- existing booking/quotation flow has to change — this table adds a
-- shared, queryable mirror on top of it, keyed by the same reservation
-- id the client already generates (e.g. "CVR-2026-12345").
--
-- Safe to re-run.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

CREATE TABLE IF NOT EXISTS reservations (
    id VARCHAR(40) PRIMARY KEY,           -- client-generated id, e.g. CVR-2026-12345
    user_id INT DEFAULT NULL,             -- set when the guest is logged in
    owner_session_key VARCHAR(64) DEFAULT NULL, -- device-scoped owner key for guests without an account
    booking_type ENUM('room', 'event') NOT NULL,
    package_id VARCHAR(100) DEFAULT NULL,
    room_name VARCHAR(191) DEFAULT NULL,
    check_in DATE DEFAULT NULL,
    check_out DATE DEFAULT NULL,
    preferred_date DATE DEFAULT NULL,     -- for events (single-day)
    customer_name VARCHAR(191) NOT NULL DEFAULT '',
    email VARCHAR(191) NOT NULL DEFAULT '',
    reservation_status VARCHAR(60) NOT NULL DEFAULT '',
    payment_status VARCHAR(60) NOT NULL DEFAULT '',
    total_numeric DECIMAL(10,2) DEFAULT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'PHP',
    payload_json LONGTEXT NOT NULL,       -- full reservation object as built client-side
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_res_dates (booking_type, check_in, check_out),
    INDEX idx_res_preferred_date (booking_type, preferred_date),
    INDEX idx_res_user (user_id),
    INDEX idx_res_email (email),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
