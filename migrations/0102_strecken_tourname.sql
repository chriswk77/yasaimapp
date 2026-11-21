-- The tracks take the finds' name for their tour (13.09.2026,
-- „ich habe gestern die Tagestour Nummer fünf umbenannt in nur
-- Tagestour, bei der Wegstrecke steht aber noch eine Verbindung zu
-- Tagestour Nummer fünf").
--
-- map_tracks.tour is a copy of a label the finds own (tracks.php,
-- track_tour_name_known explains the rule). From today a rename reaches
-- the tracks as it happens; this pass brings the stock into line once —
-- every track whose tour has finds takes their name. updated_at MOVES
-- here, on purpose: the name is part of what a device shows, and only a
-- moved fingerprint fetches it a fresh copy (unlike 0099 and 0101, which
-- changed nothing a device shows by name).
UPDATE map_tracks t
   SET t.tour = (
        SELECT p.tour FROM map_points p
         WHERE p.user_id = t.user_id AND p.tour_id = t.tour_id
           AND p.tour IS NOT NULL AND p.deleted_at IS NULL
         ORDER BY p.updated_at DESC, p.id DESC
         LIMIT 1),
       t.updated_at = NOW()
 WHERE t.tour_id IS NOT NULL
   AND EXISTS (
        SELECT 1 FROM map_points p
         WHERE p.user_id = t.user_id AND p.tour_id = t.tour_id
           AND p.tour IS NOT NULL AND p.deleted_at IS NULL
           AND (t.tour IS NULL OR t.tour <> p.tour));
