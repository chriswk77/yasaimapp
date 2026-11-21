-- THE WAY A FIND WAS KEYED (14.10.2026).
--
-- „ich stelle mir das so vor, dass man mehrere Bücher zu Hilfe
-- nehmen kann und alle Schlüsselwege werden gespeichert. Bei der Art, dass
-- das dokumentiert ist, wie man auf den Namen kommt." And for the circle of
-- a find: „für jeden Mitbesitzer den individuellen Weg speichern, sodass der
-- eigene Weg nicht überschrieben wird und man darüber diskutieren kann."
--
-- ONE ROW PER FIND AND PERSON: the whole walk of the identification module
-- (assets/js/keys.js, saveWalk — every way through every book, the dead
-- ends, the rejected and the confirmed result) as JSON, written by its
-- keyer only and read by everyone who sees the find (includes/point_keyings.php).
-- The species last confirmed is kept beside it; the find's own species field
-- takes it over through the ordinary point change, not through this table.

CREATE TABLE map_point_keyings (
    point_id       INT UNSIGNED NOT NULL,
    user_id        INT UNSIGNED NOT NULL COMMENT 'whose way — only he writes it',
    walk           MEDIUMTEXT   NOT NULL COMMENT 'the identification walk as JSON (keys.js saveWalk)',
    confirmed_name VARCHAR(255)     NULL COMMENT 'the species last confirmed on this walk',
    confirmed_at   DATETIME         NULL,
    created_at     DATETIME     NOT NULL,
    updated_at     DATETIME     NOT NULL,
    PRIMARY KEY (point_id, user_id),
    KEY idx_point_keyings_user (user_id),
    CONSTRAINT fk_point_keyings_point FOREIGN KEY (point_id) REFERENCES map_points (id) ON DELETE CASCADE,
    CONSTRAINT fk_point_keyings_user  FOREIGN KEY (user_id)  REFERENCES map_users (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
