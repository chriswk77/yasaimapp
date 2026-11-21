-- Main list per user.
--
-- When a list was deleted, its points used to be left without any assignment
-- and only showed up under "Ohne Liste". A list marked as the main list
-- catches them instead, so that no find spot falls into a no-man's-land.
--
-- Exactly one list per user carries the mark; the code in
-- includes/daten.php ensures that, not the database — MySQL has no partial
-- index that could enforce "at most one row with the value 1 per user_id".

ALTER TABLE map_lists
    ADD COLUMN ist_hauptliste TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER symbol;

-- Looking up the main list when deleting and when creating points.
ALTER TABLE map_lists
    ADD INDEX idx_hauptliste (user_id, ist_hauptliste);
