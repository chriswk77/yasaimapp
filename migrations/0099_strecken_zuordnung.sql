-- A track belongs to an outing (12.09.2026: „Können die
-- Wegstrecken auch einer Tagestour oder einer Exkursion zugeordnet werden?
-- … Deshalb wäre die Zuordnung wichtig").
--
-- THE SAME THREE FIELDS THE POINTS CARRY, and deliberately the same: tour
-- and tour_id as on map_points (migration 0079 — minted on the device,
-- twelve hex characters, the name a free label), excursion_id as on
-- map_points (0068). A track that says „t:3f2a…" means the very outing a
-- point says it with; nothing has to be translated between the two.
--
-- WHY ON THE TRACK AND NOT DERIVED EVERY TIME. Deriving „the tour of that
-- day" is cheap once and expensive on every listing, and it is also not
-- always right — two outings on one day, a night walk across midnight.
-- Stored, the assignment can be corrected by hand and stays corrected.
-- The derivation still exists (track_assignment_derive in tracks.php):
-- it fills the fields when an upload carries none, so the app may stay
-- what it is, a recorder that knows no tours. Sends the app a tour, that
-- wins.
ALTER TABLE map_tracks
    ADD COLUMN tour         VARCHAR(120) NULL COMMENT 'Name der Tagestour, wie auf map_points' AFTER device,
    ADD COLUMN tour_id      CHAR(12)     NULL COMMENT 'vom Gerät geprägt, wie map_points.tour_id' AFTER tour,
    ADD COLUMN excursion_id INT UNSIGNED NULL COMMENT 'wie map_points.excursion_id' AFTER tour_id,
    ADD KEY idx_track_tour (user_id, tour_id),
    ADD KEY idx_track_excursion (excursion_id);

-- THE STOCK IS ASSIGNED ONCE, the way the derivation would do it at
-- upload — so no walk recorded before today stands there unassigned while
-- the ones after it are. updated_at is NOT touched: the device stores
-- these fields as part of the record's shape, and a shape change is
-- announced by VERSION in trackstore.js, not by fingerprints.
--
-- The tour: the outing the user's finds of that day carry — any of them,
-- MySQL picks one; where a day had two outings the hand correction in the
-- track's popup is the way, the same as for a fresh upload.
UPDATE map_tracks t
   SET t.tour_id = (
        SELECT p.tour_id FROM map_points p
         WHERE p.user_id = t.user_id AND p.deleted_at IS NULL AND p.tour_id IS NOT NULL
           AND DATE(COALESCE(p.found_at, p.created_at)) = DATE(t.started_at)
         ORDER BY COALESCE(p.found_at, p.created_at)
         LIMIT 1),
       t.tour = (
        SELECT p.tour FROM map_points p
         WHERE p.user_id = t.user_id AND p.deleted_at IS NULL AND p.tour_id IS NOT NULL
           AND DATE(COALESCE(p.found_at, p.created_at)) = DATE(t.started_at)
         ORDER BY COALESCE(p.found_at, p.created_at)
         LIMIT 1)
 WHERE t.started_at IS NOT NULL AND t.tour_id IS NULL;

-- The excursion: one the user leads, whose window overlaps the walk.
UPDATE map_tracks t
   SET t.excursion_id = (
        SELECT e.id FROM map_excursions e
         WHERE e.leader_user_id = t.user_id
           AND e.starts_at <= COALESCE(t.ended_at, t.started_at)
           AND e.ends_at   >= t.started_at
         ORDER BY e.starts_at DESC
         LIMIT 1)
 WHERE t.started_at IS NOT NULL AND t.excursion_id IS NULL;
