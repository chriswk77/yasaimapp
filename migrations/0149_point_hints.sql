-- A FIND SHOWN TO A FRIEND — „Direkt teilen" (02.10.2026).
--
-- „Dass man einen Einzelfund auch mit jemandem teilen kann … die direkt
-- geteilten Punkte sind sowas wie Hinweise. Diesen Hinweis kann der Freund
-- wieder entfernen. Dabei wird der Punkt aber nicht aus den anderen Listen,
-- die ich mit ihm teile, entfernt. Also dass man ihm eine Stelle zeigen
-- kann, und er sie einfach bei Geteilt abrufen kann."
--
-- A HINT HANGS ON NO LIST, and that is the whole design. The Freigaben
-- (map_containers) share LISTS; a single find would have had to go into a
-- list of its own, and that list would have stood in the holder's tree and
-- in his filing. Here one row says: this find, shown to this friend, by
-- this person. The receiver sees it under „Geteilt" in a row „Von <Name>",
-- read only; taking the hint away removes this row and nothing else — the
-- find stays in every list its holder shares with him.
--
-- user_id IS WHO SHOWED IT, the holder at the time. A find can change its
-- holder (point_delete hands over to the most senior co-owner); the hint
-- still came from whoever set it, and the receiver's row is named by him.
--
-- Everything goes with the point or either person (CASCADE). A friendship
-- that ends takes its hints along in friendship_remove(); the reading side
-- asks for an accepted friendship as well, so nothing is shown in between.
--
-- THE CODE CAN GO LIVE FIRST: point_hints_ready() asks for this table, and
-- until it exists the tile, the row and the tab stay away.

CREATE TABLE map_point_hints (
    point_id     INT UNSIGNED NOT NULL,
    recipient_id INT UNSIGNED NOT NULL COMMENT 'the friend the find is shown to',
    user_id      INT UNSIGNED NOT NULL COMMENT 'who showed it — the holder at the time',
    created_at   DATETIME     NOT NULL,
    PRIMARY KEY (point_id, recipient_id),
    KEY idx_point_hints_recipient (recipient_id),
    KEY idx_point_hints_user (user_id),
    CONSTRAINT fk_point_hints_point     FOREIGN KEY (point_id)     REFERENCES map_points (id) ON DELETE CASCADE,
    CONSTRAINT fk_point_hints_recipient FOREIGN KEY (recipient_id) REFERENCES map_users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_point_hints_user      FOREIGN KEY (user_id)      REFERENCES map_users (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
