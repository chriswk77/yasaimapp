-- CO-OWNERSHIP AND CLOUD COPIES (11.09.2026). A find is a shared
-- record: whoever attaches a photo becomes a co-owner, and every co-owner
-- with a cloud holds the COMPLETE picture set as his own copy — „alle
-- Bilder von allen Bearbeitern immer auch gleich in die eigene Cloud
-- mitgespielt … ein kompletter Fundsatz in jeder Cloud". Leaving is the
-- door, not the bucket: the find stays with the others.
--
-- NOTHING EXISTING CHANGES SHAPE. map_points.user_id stays the HOLDER, so
-- every path that reads „my points" keeps working; the co-owners stand in
-- their own table, and the creator gets no row there — he is the holder.
-- Both tables are gated by table_exists() in the code: the code goes live
-- before the migration.

-- The co-owners. list_id is „jeder mit seiner eigenen Ablage": the holder
-- files the find in his list (map_point_lists), every co-owner in his own
-- one here. deleted_at is his trash — set means „ausgestiegen", and it is
-- reversible; a row without it means „dabei".
CREATE TABLE map_point_owners (
    point_id   INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    list_id    INT UNSIGNED NULL     COMMENT 'die eigene Ablage des Mitbesitzers',
    since      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME     NULL     COMMENT 'sein Papierkorb — gesetzt = ausgestiegen, umkehrbar',
    PRIMARY KEY (point_id, user_id),
    KEY idx_point_owners_user (user_id, deleted_at),
    CONSTRAINT fk_point_owners_point FOREIGN KEY (point_id) REFERENCES map_points (id) ON DELETE CASCADE,
    CONSTRAINT fk_point_owners_user  FOREIGN KEY (user_id)  REFERENCES map_users  (id) ON DELETE CASCADE,
    CONSTRAINT fk_point_owners_list  FOREIGN KEY (list_id)  REFERENCES map_lists  (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The copies. A picture is ONE record (map_cloud_files or map_photos —
-- there live title, scale, measures, once); this table holds where else
-- its bytes lie. origin_kind/origin_id name the record, user_id the cloud
-- the copy went to. The photographer's own storage is the origin row
-- itself and never appears here.
--
-- status is the worker's state machine (includes/cloud_copies_content.php):
--   waiting  → to be copied; attempts/tried_at drive the back-off
--   copied   → fileid/folderid/path/size describe the copy
--   given_up → after CLOUD_COPIES_MAX_ATTEMPTS, error says why; visible in
--              the tab, never retried on its own
--   deleting → the origin is gone; the worker removes the copy, then the row
CREATE TABLE map_cloud_copies (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    origin_kind ENUM('cloud', 'photo') NOT NULL,
    origin_id   INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL COMMENT 'der Empfänger — in seine Cloud geht die Kopie',
    provider    VARCHAR(20)  NOT NULL DEFAULT 'pcloud',
    fileid      BIGINT UNSIGNED NULL,
    folderid    BIGINT UNSIGNED NULL,
    path        VARCHAR(512) NULL,
    size        INT UNSIGNED NULL,
    status      ENUM('waiting', 'copied', 'given_up', 'deleting') NOT NULL DEFAULT 'waiting',
    attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    tried_at    DATETIME NULL,
    error       VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    copied_at   DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cloud_copies_origin_user (origin_kind, origin_id, user_id),
    KEY idx_cloud_copies_status (status, tried_at),
    CONSTRAINT fk_cloud_copies_user FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
