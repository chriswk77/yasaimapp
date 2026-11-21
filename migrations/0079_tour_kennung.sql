-- Tours get an identity of their own (24.08.2026: „vergib doch
-- Tour-IDs, dann können wir auch gleiche Namen nutzen").
--
-- UNTIL NOW THE NAME WAS THE TOUR. That was the deliberate trade of
-- migration 0071 — a label needs no table and survives a dead zone —, but
-- it made two outings of the same name ONE outing: „Tagestour" of today
-- and of last week merged, and the counter „(2)" existed only to prevent
-- exactly that. With a name that no longer carries the date (24.08.2026)
-- the collision became the normal case, not the exception.
--
-- THE KENNUNG IS MINTED ON THE DEVICE, like client_id on the points
-- (migration 0020) and on the lists (0021). That is what keeps the offline
-- promise of 0071: a tour begun without reception is complete at birth —
-- no provisional id, no rewriting on sync, no second identity mechanism
-- beside the one the points already have.
--
-- THE NAME STAYS what it always was: a label, now freely repeatable and
-- renamable without touching identity.
ALTER TABLE map_points
    ADD COLUMN tour_id CHAR(12) NULL COMMENT 'vom Gerät geprägt, Identität der Tour' AFTER tour,
    ADD KEY idx_point_tour_id (tour_id);

-- The stock keeps its outings whole: every distinct name of a user becomes
-- one tour. That is exactly the reading that was true until today, so
-- nothing merges or splits in the moment of the migration — MD5 over user
-- and name gives each one a stable twelve characters.
UPDATE map_points
   SET tour_id = SUBSTRING(MD5(CONCAT(user_id, ':', tour)), 1, 12)
 WHERE tour IS NOT NULL AND tour <> '' AND tour_id IS NULL;
