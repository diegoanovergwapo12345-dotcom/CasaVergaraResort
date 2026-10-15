-- Allow half-star ratings (0.5–5.0 in 0.5 steps) on reviews.
-- Run once on the live database.

ALTER TABLE reviews MODIFY rating DECIMAL(2,1) NOT NULL;
ALTER TABLE reviews DROP CHECK chk_review_rating;
ALTER TABLE reviews ADD CONSTRAINT chk_review_rating CHECK (rating BETWEEN 0.5 AND 5);
