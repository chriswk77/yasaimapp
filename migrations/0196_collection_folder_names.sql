-- An Aufsammlung's cloud folder carries its box number (13.11.2026).
--
-- „Wieso hat denn meine Aufsammlung von heute eine
-- Yasai-Map-Nummer bekommen? Ich dachte, die Aufsammlungen bekommen eigene
-- Nummern." An Aufsammlung lies in a species list like a find, and every
-- place that asked only „is it in a species list?" gave it a find's
-- „Y-<id>" mark — the sidebar row, the Organisieren column, the search,
-- the exports and the folder its pictures went into in the cloud. The code
-- now uses the box number („CW-A-2026-03") everywhere.
--
-- WHAT THIS DOES: the folders already made carry the old mark, and the
-- names worker renames only what it is told about. Every Aufsammlung is
-- put on its list once (map_cloud_names_due, the same row
-- cloud_names_touch() writes), and the five-minute bundle moves its
-- pictures into the folder with the box number. Nothing else changes.
INSERT INTO map_cloud_names_due (point_id, due_at)
    SELECT id, NOW(6) FROM map_points
     WHERE is_collection = 1 AND deleted_at IS NULL
    ON DUPLICATE KEY UPDATE due_at = NOW(6);
