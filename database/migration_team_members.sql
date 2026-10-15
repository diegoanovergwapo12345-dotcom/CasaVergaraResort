-- ============================================================
-- Migration: editable "Our Team" (Meet Our Leadership) content
--
-- The team cards on pages/about.html used to be hardcoded in a JS array.
-- They now come from this table, so Admin and Staff (event_manager)
-- accounts can edit them from Admin > Our Team (pages/admin/team.html).
--
-- Safe to re-run. The seed rows below match the original hardcoded
-- profiles, so the About page looks the same until someone edits it.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    role VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL DEFAULT '',
    photo_url VARCHAR(500) NOT NULL DEFAULT '',
    bio TEXT NULL,
    -- One highlight per line (rendered as the checklist in the profile modal).
    facts TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- If team_members already existed from before this column was added
-- (e.g. you ran this migration's earlier version), add it now.
ALTER TABLE team_members
  ADD COLUMN IF NOT EXISTS email VARCHAR(190) NOT NULL DEFAULT '' AFTER role;

-- The guest-facing "Get in touch" button on the About page's team pop-up
-- opens a Gmail compose window addressed straight to whichever leader's
-- profile is open, using this email. Admin/staff can change it any time
-- from Admin > Our Team.
INSERT INTO team_members (id, name, role, email, photo_url, bio, facts, sort_order)
VALUES
(1, 'Maria Vergara', 'Founder & CEO', 'maria.vergara@casavergararesort.com',
 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=900&q=80',
 'Maria founded Casa Vergara in 2021 with a simple belief: guests should feel at home the moment they arrive. She still greets arrivals personally most weekends.',
 '15+ years in hospitality leadership\nDrives our sustainability program\nHospitality Excellence awardee, 2023', 1),
(2, 'Carlos Rodriguez', 'General Manager', 'carlos.rodriguez@casavergararesort.com',
 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=900&q=80',
 'Carlos keeps every corner of the resort running smoothly, from housekeeping standards to large event turnovers, without ever losing the warm welcome.',
 'Oversees daily resort operations\nLeads a team of 120+ staff\nCertified Hotel Administrator', 2),
(3, 'Isabella Santos', 'Guest Relations', 'isabella.santos@casavergararesort.com',
 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=900&q=80',
 'Isabella is the person behind the little touches: the anniversary cake, the early check-in, the room with the better sunset view.',
 'Handles VIP and event guests\nSpeaks English, Filipino and Spanish\n98% guest satisfaction rating', 3),
(4, 'David Chen', 'Executive Chef', 'david.chen@casavergararesort.com',
 'https://images.unsplash.com/photo-1566492031773-4f4e44671857?auto=format&fit=crop&w=900&q=80',
 'David builds our menus around what local farmers and fishers bring in that morning, blending Filipino flavors with modern technique.',
 'Leads all resort dining outlets\nFarm-to-table sourcing advocate\nTrained in Singapore and Manila', 4)
ON DUPLICATE KEY UPDATE id = id;
