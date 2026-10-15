-- ------------------------------------------------------------
-- C.A.S.A. (guest-facing AI assistant) settings — single row,
-- editable from Admin > C.A.S.A. by 'admin' accounts only.
-- Run this once against an existing database:
--   mysql -u root casa_vergara < database/migration_ai_assistant_settings.sql
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_assistant_settings (
    id INT PRIMARY KEY DEFAULT 1,
    display_name VARCHAR(50) NOT NULL DEFAULT 'C.A.S.A.',
    tagline VARCHAR(150) NOT NULL DEFAULT 'Client Assistance & Service Associate',
    greeting VARCHAR(500) NOT NULL DEFAULT 'Hi! I''m C.A.S.A. — your Client Assistance & Service Associate. Ask me anything about rooms, rates, bookings or payments.',
    persona_instructions TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT ai_assistant_settings_single_row CHECK (id = 1)
) ENGINE=InnoDB;

INSERT INTO ai_assistant_settings (id, display_name, tagline, greeting)
VALUES (1, 'C.A.S.A.', 'Client Assistance & Service Associate',
        'Hi! I''m C.A.S.A. — your Client Assistance & Service Associate. Ask me anything about rooms, rates, bookings or payments.')
ON DUPLICATE KEY UPDATE id = id;
