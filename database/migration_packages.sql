-- ============================================================
-- Migration: DB-backed room & event packages.
--
-- Previously, the bookable catalog (js/booking-data.js:
-- ROOM_PACKAGES / EVENT_PACKAGE_CATALOG / ROOM_PACKAGE_ADDONS) was
-- hardcoded in the frontend, and Admin > Packages edits were saved to
-- the browser's localStorage only — never synced to a server, never
-- visible on another device, and invisible to server-side code like
-- the AI assistant.
--
-- This migration creates real tables so packages can be managed like
-- FAQs (admin API + DB), and seeds them with the exact data that was
-- previously hardcoded, so nothing changes for guests on day one.
--
-- Run once:
--   mysql -u root casa_vergara < database/migration_packages.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS room_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    tagline VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    price_per_night DECIMAL(10,2) NOT NULL DEFAULT 0,
    capacity_min INT NOT NULL DEFAULT 1,
    capacity_max INT NOT NULL DEFAULT 1,
    area_sqm INT DEFAULT NULL,
    bed_setup VARCHAR(150) DEFAULT NULL,
    -- JSON arrays, e.g. ["Private pool","Garden view"]
    inclusions_json TEXT DEFAULT NULL,
    -- JSON array of {label, price}
    addons_json TEXT DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    standard_check_in VARCHAR(20) NOT NULL DEFAULT '15:00',
    standard_check_out VARCHAR(50) NOT NULL DEFAULT 'Anytime (flexible)',
    availability_note VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    included_pax INT NOT NULL DEFAULT 0,
    is_custom TINYINT(1) NOT NULL DEFAULT 0,
    -- JSON array of strings
    inclusions_json TEXT DEFAULT NULL,
    -- JSON array of {id,label,colors}
    motifs_json TEXT DEFAULT NULL,
    -- JSON array of {id,label,price,category}
    addons_json TEXT DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---- Seed: exact mirror of the previous hardcoded catalog ----

INSERT INTO room_packages
    (slug, title, tagline, description, price_per_night, capacity_min, capacity_max, area_sqm, bed_setup, inclusions_json, addons_json, image_path, availability_note, sort_order)
VALUES
('deluxe-villa', 'Deluxe Room', 'Private pool villa with garden views',
 'Spacious 85 m² villa with a private pool, king bed, and dedicated butler service — ideal for families or small groups seeking privacy.',
 6500.00, 1, 4, 85, '1 King Bed',
 '["Private pool","Garden view","Butler service","Daily housekeeping","Wi-Fi"]',
 '[{"label":"Late checkout (until 2:00 PM)","price":800},{"label":"In-villa breakfast setup","price":1200},{"label":"Celebration room décor","price":1500}]',
 '../images/booking-photo-1.png', 'Available year-round. Minimum 1 night. Peak season rates may apply.', 1),

('single-room', 'Single Room', 'Cozy stay for solo travelers',
 'Comfortable 28 m² room designed for one guest, with a single bed, workspace, and fast Wi-Fi — great for quick stays and business trips.',
 1800.00, 1, 1, 28, '1 Single Bed',
 '["Workspace","Smart TV","Coffee/tea","Wi-Fi","Daily housekeeping"]',
 '[{"label":"Late checkout (until 1:00 PM)","price":400},{"label":"Premium bedding upgrade","price":350}]',
 '../images/booking-photo-4.png', 'Limited rooms available. Best for 1 guest.', 2),

('ocean-view-suite', 'Ocean View Suite', 'Panoramic ocean views with balcony',
 '65 m² suite with king or twin setup, private balcony overlooking the sea, minibar, and premium bath amenities.',
 3800.00, 1, 3, 65, '1 King or 2 Twin',
 '["Ocean view","Balcony","Mini bar","Rain shower","Wi-Fi"]',
 '[{"label":"Balcony breakfast for two","price":950},{"label":"Anniversary décor package","price":1200}]',
 '../images/booking-photo-2.png', 'High demand — book early for weekends and holidays.', 3),

('beach-bungalow', 'Beach Bungalow', 'Steps from the sand',
 'Cozy 50 m² bungalow with queen bed, direct beach access, patio seating, and breakfast included.',
 3200.00, 1, 2, 50, '1 Queen Bed',
 '["Beach access","Private patio","Breakfast","Wi-Fi"]',
 '[{"label":"Beach picnic setup","price":1100},{"label":"Private bonfire arrangement","price":2000}]',
 '../images/booking-photo-3.png', 'Limited units. Subject to tide and weather advisories.', 4),

('garden-view-room', 'Garden View Room', 'Tranquil garden outlook',
 '42 m² room with queen bed, smart TV, coffee maker, and quiet garden views.',
 2500.00, 1, 2, 42, '1 Queen Bed',
 '["Garden view","Smart TV","Coffee maker","Wi-Fi"]',
 '[{"label":"Garden afternoon tea","price":650}]',
 '../images/booking-photo-4.png', 'Best availability midweek.', 5),

('family-suite', 'Family Room', 'Two bedrooms and living space',
 '95 m² two-bedroom suite with king + twin beds, two bathrooms, living area, and kitchenette — perfect for families.',
 4800.00, 2, 6, 95, '1 King + 2 Twin',
 '["2 bathrooms","Living area","Kitchenette","Wi-Fi","Sofa bed optional"]',
 '[{"label":"Late checkout (until 2:00 PM)","price":900},{"label":"Baby crib & amenities","price":500},{"label":"Kids welcome setup","price":750}]',
 '../images/booking-photo-1.png', 'Connecting rooms available on request.', 6),

('honeymoon-suite', 'Honeymoon Suite', 'Jacuzzi and ocean outlook',
 '75 m² romantic suite with king bed, jacuzzi, ocean views, and enhanced turndown service.',
 5500.00, 1, 2, 75, '1 King Bed',
 '["Jacuzzi","Ocean view","Butler on call","Sparkling welcome","Wi-Fi"]',
 '[{"label":"Rose petal turndown","price":800},{"label":"Couples spa voucher","price":2500}]',
 '../images/booking-photo-2.png', 'Includes honeymoon amenities package.', 7)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO event_packages
    (slug, name, price, included_pax, is_custom, inclusions_json, motifs_json, addons_json, sort_order)
VALUES
('ayesha', 'Ayesha Package', 9000.00, 50, 0,
 '["2 panels theme backdrop with lights","Celebrant chair","Lighted age number","Name cut-out","Theme character","Happy birthday LED entrance balloons","Cake table & souvenir stand","Table centerpieces","Tables and chairs with cover (50 pax)"]',
 '[{"label":"Pastel Rainbow","colors":"Pink · Lavender · Mint"},{"label":"Princess Pink & Gold","colors":"Blush · Gold · White"},{"label":"Unicorn Dream","colors":"Lavender · Aqua · Silver"},{"label":"Royal Blue & Gold","colors":"Navy · Gold · Ivory"},{"label":"Custom motif","colors":"Your choice"}]',
 '[{"label":"Basic LED uplighting (4 units)","price":1500,"category":"lights"},{"label":"Stage spotlight set (2 units)","price":3000,"category":"lights"},{"label":"Basic sound system (speaker + mic)","price":2000,"category":"sound"},{"label":"Wireless microphone (extra unit)","price":500,"category":"sound"},{"label":"Extra backdrop panel","price":1500,"category":"extra"},{"label":"Premium balloon arch upgrade","price":2000,"category":"extra"},{"label":"Photo corner setup","price":1200,"category":"extra"},{"label":"Enhanced cake table décor","price":800,"category":"extra"}]',
 1),

('anica', 'Anica Package', 13500.00, 50, 0,
 '["3 panels theme backdrop with lights","Upgraded decorations","Celebrant chair","Lighted age number","Happy birthday LED","Name cut-out","Theme character","Celebrant name standee","Basic ceiling balloons","Entrance arch balloon with garland","Cake stand & souvenir stand","Buffet table setup & centerpieces","Tables and chairs with cover (50 pax)"]',
 '[{"label":"Elegant Gold & White","colors":"Champagne · Gold · White"},{"label":"Garden Blush","colors":"Rose · Sage · Cream"},{"label":"Midnight Glam","colors":"Black · Silver · Blue"},{"label":"Tropical Fiesta","colors":"Coral · Teal · Yellow"},{"label":"Custom motif","colors":"Your choice"}]',
 '[{"label":"Fairy light ceiling drape","price":2500,"category":"lights"},{"label":"LED dance floor accent","price":3500,"category":"lights"},{"label":"Premium sound system + DJ mixer","price":5000,"category":"sound"},{"label":"Premium ceiling balloon install","price":2500,"category":"extra"},{"label":"Additional theme character","price":1800,"category":"extra"},{"label":"On-site catering coordination","price":2000,"category":"catering"}]',
 2),

('amarah', 'Amarah Package', 6500.00, 30, 0,
 '["1 panel basic theme backdrop","Celebrant chair","Age number standee","Name cut-out","Theme character","Cake stand","Entrance balloon standee","Table centerpieces","Tables and chairs cover (30 pax)"]',
 '[{"label":"Soft Pastel","colors":"Peach · Lilac · Sky blue"},{"label":"Classic Red & Gold","colors":"Red · Gold · White"},{"label":"Minimal White & Greenery","colors":"White · Green · Wood"},{"label":"Custom motif","colors":"Your choice"}]',
 '[{"label":"Basic LED uplighting (4 units)","price":1500,"category":"lights"},{"label":"Basic sound system (speaker + mic)","price":2000,"category":"sound"},{"label":"Add second backdrop panel","price":2000,"category":"extra"},{"label":"Balloon entrance upgrade","price":1500,"category":"extra"},{"label":"Dessert table styling","price":1000,"category":"extra"}]',
 3),

('custom', 'Custom Package', 3500.00, 100, 1,
 '["Venue setup crew & basic tables/chairs","On-site event coordinator","Post-event cleanup","Build your own look: pick any lighting, sound, motif, and decor add-ons below"]',
 '[{"label":"Pastel Rainbow","colors":"Pink · Lavender · Mint"},{"label":"Princess Pink & Gold","colors":"Blush · Gold · White"},{"label":"Unicorn Dream","colors":"Lavender · Aqua · Silver"},{"label":"Royal Blue & Gold","colors":"Navy · Gold · Ivory"},{"label":"Elegant Gold & White","colors":"Champagne · Gold · White"},{"label":"Garden Blush","colors":"Rose · Sage · Cream"},{"label":"Midnight Glam","colors":"Black · Silver · Blue"},{"label":"Tropical Fiesta","colors":"Coral · Teal · Yellow"},{"label":"Classic Red & Gold","colors":"Red · Gold · White"},{"label":"Minimal White & Greenery","colors":"White · Green · Wood"},{"label":"Custom motif","colors":"Your choice"}]',
 '[{"label":"Basic LED uplighting (4 units)","price":1500,"category":"lights"},{"label":"Fairy light ceiling drape","price":2500,"category":"lights"},{"label":"Stage spotlight set (2 units)","price":3000,"category":"lights"},{"label":"LED dance floor accent","price":3500,"category":"lights"},{"label":"Basic sound system (speaker + mic)","price":2000,"category":"sound"},{"label":"Premium sound system + DJ mixer","price":5000,"category":"sound"},{"label":"Wireless microphone (extra unit)","price":500,"category":"sound"},{"label":"Theme backdrop panel (per panel)","price":1500,"category":"extra"},{"label":"LED balloon entrance arch","price":2000,"category":"extra"},{"label":"Photo corner setup","price":1200,"category":"extra"},{"label":"Styled cake & souvenir table","price":800,"category":"extra"},{"label":"Celebrant name cut-out","price":700,"category":"extra"},{"label":"Lighted age number","price":900,"category":"extra"},{"label":"Fog machine effect","price":1800,"category":"extra"},{"label":"Theme character appearance","price":1800,"category":"extra"},{"label":"Buffet-style catering (per pax)","price":250,"category":"catering"},{"label":"On-site catering coordination","price":2000,"category":"catering"}]',
 4)
ON DUPLICATE KEY UPDATE slug = slug;
