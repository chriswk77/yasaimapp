-- MOVING TO ANOTHER CLOUD (26.09.2026): the „Umzugswerkzeug" —
-- every picture YASAIMAPP laid into one cloud goes to another, as a SWITCH.
-- From the moment the user agrees at the new provider, that one IS the
-- cloud: new photos, the backup, the one tile in the Cloud tab. The old one
-- is no longer connected; YASAIMAPP only keeps its key in the background
-- until the last picture has arrived, and deletes it then. His rule of
-- 27.08.2026 stands — „man kann immer nur zu einem connected sein"; the old
-- key is a loan for the move, not a second connection
-- (includes/cloud_moves.php).
--
-- NOTHING EXISTING CHANGES SHAPE. The old account row stays in
-- map_cloud_accounts while its move runs, and the move row is what marks it
-- as retiring: cloud_connections() leaves out the provider an open move
-- names as from_provider, cloud_storage_for() still opens it for the
-- pictures. Both tables are gated by table_exists() in the code: the code
-- goes live before the migration.

-- One row per move.
--   running → pictures are being carried; the old key is kept
--   done    → everything has arrived; the old key is deleted
--   closed  → ended by hand with pictures left behind; the old key is
--             deleted, and those pictures stay in the old cloud
-- full_since: the new cloud said „full" — the whole move waits, and every
-- run asks the new cloud once how much room there is.
-- problem: why the move cannot go on right now although nothing is full —
-- a cloud that does not answer, a key that no longer holds. The panel says
-- it; the next run simply tries again.
CREATE TABLE map_cloud_moves (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    from_provider VARCHAR(20)  NOT NULL,
    to_provider   VARCHAR(20)  NOT NULL,
    status        ENUM('running', 'done', 'closed') NOT NULL DEFAULT 'running',
    full_since    DATETIME NULL,
    problem       VARCHAR(255) NULL,
    started_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cloud_moves_user (user_id, status),
    CONSTRAINT fk_cloud_moves_user FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per picture to carry. kind names the table, row_id its row:
--   photo → map_photos (voucher photos)
--   cloud → map_cloud_files (recordings)
--   scale → map_photo_scales (calibration images)
--   copy  → map_cloud_copies (the copies of the others' pictures)
-- status: waiting → moved, or stuck — error says why, and a stuck picture
-- is tried again a day later. The rows stay after the end: they are the
-- count the Cloud tab's footnote reads.
CREATE TABLE map_cloud_move_items (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    move_id   INT UNSIGNED NOT NULL,
    kind      ENUM('photo', 'cloud', 'scale', 'copy') NOT NULL,
    row_id    INT UNSIGNED NOT NULL,
    size      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status    ENUM('waiting', 'moved', 'stuck') NOT NULL DEFAULT 'waiting',
    attempts  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    tried_at  DATETIME NULL,
    error     VARCHAR(255) NULL,
    moved_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cloud_move_items (move_id, kind, row_id),
    KEY idx_cloud_move_items_status (move_id, status),
    CONSTRAINT fk_cloud_move_items_move FOREIGN KEY (move_id) REFERENCES map_cloud_moves (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
