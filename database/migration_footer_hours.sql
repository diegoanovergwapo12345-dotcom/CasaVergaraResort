-- Clear stored 24/7 footer copy so the public site no longer promises round-the-clock support.
UPDATE site_text
SET text_value = 'We will respond as soon as available'
WHERE text_key = 'footer_hours'
  AND (
    text_value LIKE '%24/7%'
    OR text_value LIKE '%24 hours%'
    OR LOWER(text_value) LIKE '%anytime%'
  );
