-- The joint lists' own trash, and the chat lines about every change —
-- step 2 of the joint lists (21./22.09.2026: „das Löschen darf
-- nicht stillschweigend sein; ein gelöschter Punkt muss bei allen
-- verschwinden und im Papierkorb landen, bei allen; jeder bekommt die
-- Möglichkeit, ihn jederzeit zurückzusetzen").
--
-- THE TRASH HOLDS FILINGS, NOT POINTS (option A, 22.09.2026). Deleting in
-- a joint list takes the point's filing OUT OF THAT LIST and lays it
-- here: who took it, when. Restoring puts exactly this filing back. A
-- point in two joint lists has two filings and can lie in one trash and
-- one list at the same time. The record itself lives as long as any
-- filing lives; only a point without a single filing left is in the
-- trash as a whole (map_points.deleted_at), and it revives from any
-- list trash with that one filing. Nothing here is ever purged by a
-- member: „Endgültig" and „Papierkorb leeren" pass a point by while a
-- row of it lies in this table.
--
-- No DELETE CASCADE on the point: a row here KEEPS the point (see
-- point_delete_permanently). Cascade on the list — a list that is gone
-- for good takes its filings' history along, as it takes the filings.
CREATE TABLE map_list_trash (
    point_id   INT UNSIGNED NOT NULL,
    list_id    INT UNSIGNED NOT NULL,
    removed_by INT UNSIGNED NOT NULL COMMENT 'the member who took the filing out',
    removed_at DATETIME NOT NULL,
    PRIMARY KEY (point_id, list_id),
    KEY list_id (list_id),
    CONSTRAINT fk_list_trash_list FOREIGN KEY (list_id) REFERENCES map_lists (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- THE KIND OF A CHAT MESSAGE: 'chat' is what somebody wrote, 'change' is
-- what the map wrote about a joint point — „hat „Boletus edulis" geändert:
-- Titel, Notiz". Both stand in the one conversation of the share, because
-- there is where the members already look; the kind lets the browser draw
-- the second quietly and lets nobody mistake it for a spoken word.
ALTER TABLE map_container_messages
    ADD kind ENUM('chat', 'change') NOT NULL DEFAULT 'chat';
