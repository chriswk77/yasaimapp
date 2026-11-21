-- WORKING NAMES — species no list knows yet (05.11.2026).
--
-- Whoever works on a genus has species that are not published: in no
-- DGfM list, in no GBIF, often with a label only the working group
-- understands („Inocybe 'violacea' ad int.", „Cortinarius sp. nov. 3").
-- Until now such a title saved fine and then fell through every floor
-- without a word: no reference, so no German name or Red List, the DGfM
-- delivery dropped it, „sp. nov. 1" made the determination „unbestimmt",
-- and the cloud filed „sp. nov. 1" and „sp. nov. 2" in one „Inocybe spec."
-- heap. „das widerspricht natürlich dem, dass man keine neuen
-- Arten hier bearbeiten kann und mit Freunden teilen kann — Kollegen, die
-- an der gleichen Gattung arbeiten".
--
-- A WORKING NAME IS A SMALL TAXON OF ONE'S OWN: a genus the lists know
-- (so everything that runs by genus keeps working), a free part, a status
-- (ad int., nom. prov., sp. nov., ined. — stored as keys, the Latin is
-- written by the code), a note. A find whose title is that name carries
-- the reference „working:<id>" exactly as a listed one carries
-- „fungi:<taxnr>" (title_references_sync). A name a list knows cannot
-- become a working name — there would be two of one species.
--
-- It belongs to whoever made it; he shares it with friends and working
-- groups (map_working_name_viewers), who may then title their own finds
-- with it — sight, never ownership. When it is published, „Veröffentlicht
-- als …" carries every find over to the real name and keeps the old one
-- here (published_as), so an old import or archive folder still finds it.

CREATE TABLE map_working_names (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED NOT NULL COMMENT 'who made it — only he changes, shares, publishes it',
    genus            VARCHAR(120) NOT NULL COMMENT 'a genus the name lists know, in their spelling',
    label            VARCHAR(160) NOT NULL COMMENT 'the free part: nova, sp. 1, ''violacea''',
    status           VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'ad_int, nom_prov, sp_nov, ined or empty',
    name             VARCHAR(255) NOT NULL COMMENT 'genus, label and status as a title carries it',
    note             TEXT             NULL,
    published_as     VARCHAR(255)     NULL COMMENT 'the real name it went into; the finds carry that one now',
    published_at     DATETIME         NULL,
    created_at       DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_working_names_owner_name (user_id, name),
    KEY idx_working_names_name (name),
    CONSTRAINT fk_working_names_user FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE map_working_name_viewers (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    working_name_id  INT UNSIGNED NOT NULL,
    user_id          INT UNSIGNED     NULL COMMENT 'a friend',
    group_id         INT UNSIGNED     NULL COMMENT 'or a working group the owner leads',
    created_at       DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_working_name_viewers (working_name_id, user_id, group_id),
    KEY idx_working_name_viewers_user (user_id),
    KEY idx_working_name_viewers_group (group_id),
    CONSTRAINT fk_working_name_viewers_name FOREIGN KEY (working_name_id) REFERENCES map_working_names (id) ON DELETE CASCADE,
    CONSTRAINT fk_working_name_viewers_user FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_working_name_viewers_group FOREIGN KEY (group_id) REFERENCES map_groups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
