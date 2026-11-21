-- Collection containers — stage 2 of sharing.
--
-- THE MODEL. Nobody shares "their list with person X". There is a
-- container with members, and every member hangs their own lists (or
-- individual branches) into it. Points never come into being in the
-- container — every point belongs to exactly one user and lies in that
-- user's own list; what is shared is the list, not the point. The
-- container is only a bracket over other people's lists: if somebody
-- withdraws their share, their points disappear from the container without
-- anything changing about the points themselves.
--
-- THE INPUT FIELDS always come from the ROOT LIST of the sharer, no matter
-- which branch they share. In the container, points with different field
-- sets therefore stand side by side — that is intended.
CREATE TABLE map_container (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    besitzer_user_id INT UNSIGNED NOT NULL,
    name             VARCHAR(100) NOT NULL,
    erstellt_am      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY eindeutig (besitzer_user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One becomes a member individually (as a friend of the owner) or via one
-- of his working groups. Both stand in ONE table with a kind column, not
-- in two: the question "who is a member?" should be answered by exactly
-- one place.
--
-- THE GROUP REMAINS A REFERENCE, is not copied: whoever joins the group is
-- thereby in the container, whoever leaves it is out. A copy of the
-- members at the moment of adding would walk away from the group and mean
-- two kinds of truth.
CREATE TABLE map_container_mitglieder (
    container_id   INT UNSIGNED NOT NULL,
    art            ENUM('freund','gruppe') NOT NULL,
    ziel_id        INT UNSIGNED NOT NULL COMMENT 'user_id oder gruppe_id, je nach art',
    aufgenommen_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (container_id, art, ziel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The shares: which branch of which member hangs in the container.
--
-- user_id is strictly speaking derivable (the list knows its owner), but
-- stands written out because EVERY permission check needs it — and a
-- permission check that only learns via a JOIN whom something belongs to
-- is the place where one eventually forgets the JOIN.
--
-- NO RIGHTS COLUMN: in stage 2 everything foreign is read-only. The
-- switches join in stage 3 as a migration of their own.
CREATE TABLE map_container_listen (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    container_id INT UNSIGNED NOT NULL,
    list_id      INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL COMMENT 'wer geteilt hat = Besitzer der Liste',
    erstellt_am  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY eindeutig (container_id, list_id),
    KEY teiler (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
