-- Imports as a manageable unit.
--
-- UNTIL NOW an import was invisible after being applied: the points lay in
-- some lists or other, and whoever wanted to take back a botched run would
-- have had to pick them out one by one. Now every import is a BRACKET: one
-- row here, a list of its own "Import <Datum>" (for Takeout with the
-- Google lists as sublists beneath it), and every NEWLY created point
-- carries the identifier of its import. With that a whole import can be
-- taken out of the system again: its points into the trash, its lists
-- gone, the row here gone.
--
-- ONLY NEW POINTS CARRY THE IDENTIFIER. A point that already existed
-- before (same title, same spot) is merely additionally assigned to the
-- import list during the import — when taking back, it loses this
-- assignment with the list and itself stays untouched. Exactly the
-- distinction one needs when taking back, and it sits in the column, not
-- in a second table.
CREATE TABLE map_importe (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    quelle      VARCHAR(20)  NOT NULL COMMENT 'takeout, pilze, …',
    name        VARCHAR(100) NOT NULL COMMENT 'Import 12.08.2026 (2)',
    liste_id    INT UNSIGNED NULL     COMMENT 'die Wurzelliste des Imports',
    punkte_neu  INT UNSIGNED NOT NULL DEFAULT 0,
    punkte_zugeordnet INT UNSIGNED NOT NULL DEFAULT 0,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY besitzer (user_id),
    CONSTRAINT fk_importe_user FOREIGN KEY (user_id)
        REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NO FOREIGN KEY to map_importe: when taking back, the points move into
-- the trash and the import row disappears — the identifier on the point
-- then becomes meaningless and bothers nobody. A CASCADE, by contrast,
-- would delete points, a SET NULL would blur the trace before the trash
-- has decided.
ALTER TABLE map_points
    ADD import_id INT UNSIGNED NULL
        COMMENT 'der Import, aus dem dieser Punkt entstand — NULL: von Hand'
        AFTER original,
    ADD KEY import_zugehoerigkeit (import_id);
