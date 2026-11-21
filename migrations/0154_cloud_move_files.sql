-- THE FIND'S OTHER FILES MOVE ALONG TO ANOTHER CLOUD (05.10.2026).
--
-- The day the files came (0153): „Umzug in eine andere Cloud.
-- Hier kommt alles mit, alles, was über YASAIMAPP hochgeladen wurde.
-- Dafür bauen wir das." The move (cloud_moves.php) carries its pictures as
-- rows of map_cloud_move_items, and their kind is a closed list — a fifth
-- kind, 'file' for map_point_files, has to be named here before a move can
-- write one down. Until this is applied the move goes on as before and the
-- files stay in the old cloud (cloud_move_files_ready).

ALTER TABLE map_cloud_move_items
    MODIFY COLUMN kind ENUM('photo', 'cloud', 'scale', 'copy', 'file') NOT NULL;
