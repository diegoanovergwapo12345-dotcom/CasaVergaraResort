-- ============================================================
-- Migration: Site Content Manager
-- Run this ONCE if you already imported an earlier database/schema.sql
-- (i.e. your `casa_vergara` database already exists).
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
--
-- Adds a generic key/value content table so an admin can edit the
-- headline text and photos on the public website (starting with the
-- homepage) from Admin > Site Content, without touching any code.
-- Powers: api/admin/content-list.php, api/admin/content-save.php,
-- api/admin/content-photo.php, api/content/site.php,
-- pages/admin/content.html, js/site-content.js.
-- ============================================================

CREATE TABLE IF NOT EXISTS site_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_key VARCHAR(100) NOT NULL UNIQUE,
    content_type ENUM('text', 'textarea', 'image') NOT NULL DEFAULT 'text',
    section VARCHAR(100) NOT NULL DEFAULT 'General',
    label VARCHAR(150) NOT NULL,
    text_value TEXT NULL,
    image_path VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed the homepage (pages/landing.html) fields that used to be hardcoded
-- in the HTML. Safe to re-run: existing keys are left untouched.
INSERT IGNORE INTO site_content (content_key, content_type, section, label, text_value, image_path, sort_order) VALUES
('landing_hero_kicker',        'text',    'Homepage — Hero',           'Small label above the headline', 'Casa Vergara Resort', NULL, 10),
('landing_hero_title_top',     'text',    'Homepage — Hero',           'Headline — first line',          'Your Next', NULL, 20),
('landing_hero_title_highlight','text',   'Homepage — Hero',           'Headline — highlighted line',    'Coastal Escape', NULL, 30),
('landing_hero_title_bottom',  'text',    'Homepage — Hero',           'Headline — last line',           'Awaits', NULL, 40),
('landing_hero_description',   'textarea','Homepage — Hero',           'Subheading paragraph',           'Relax and unwind at Casa Vergara Resort. Enjoy comfortable rooms, refreshing pools, and peaceful surroundings.', NULL, 50),
('landing_hero_image',         'image',   'Homepage — Hero',           'Hero photo',                      NULL, 'images/booking-photo-1.png', 60),
('landing_about_eyebrow',      'text',    'Homepage — About',          'Small label above the heading',  'About Casa Vergara', NULL, 70),
('landing_about_title',        'text',    'Homepage — About',          'Heading',                         'Your Sanctuary of Luxury', NULL, 80),
('landing_about_text',         'textarea','Homepage — About',          'Paragraph',                       'Since 2021, Casa Vergara Resort has been the premier destination for discerning travelers seeking the perfect blend of luxury, comfort, and natural beauty.', NULL, 90),
('landing_gallery_image_1',    'image',   'Homepage — About gallery',  'Gallery photo 1 (Resort entrance & pool)', NULL, 'images/booking-photo-1.png', 100),
('landing_gallery_image_2',    'image',   'Homepage — About gallery',  'Gallery photo 2 (Beachfront setting)',     NULL, 'images/booking-photo-2.png', 110),
('landing_gallery_image_3',    'image',   'Homepage — About gallery',  'Gallery photo 3 (Elegant interior lounge)',NULL, 'images/booking-photo-3.png', 120),
('landing_gallery_image_4',    'image',   'Homepage — About gallery',  'Gallery photo 4 (Premium guest suite)',    NULL, 'images/booking-photo-4.png', 130),
('landing_cta_title',          'text',    'Homepage — Call to action', 'Heading',                         'Ready for Your Dream Vacation?', NULL, 140),
('landing_cta_text',           'textarea','Homepage — Call to action', 'Paragraph',                       'Book your stay today and experience luxury like never before — from sunrise pool days to unforgettable evening gatherings.', NULL, 150),
('landing_cta_image',          'image',   'Homepage — Call to action', 'Photo',                           NULL, 'images/booking-photo-2.png', 160);
