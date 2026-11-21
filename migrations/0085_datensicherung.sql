-- Datensicherung: the finds as files in the user's own storage
-- (27.08.2026).
--
-- WHAT FOR. The finds live in one database on one shared host. A backup of
-- it exists as the database dump — but that is something one has to fetch by
-- hand and then keep somewhere, and whatever has to be remembered every week
-- is not a backup. Everyone here already brings their OWN storage for the
-- voucher photos; the finds themselves can lie beside them, and then the
-- data exists twice in two places without anybody doing anything.
--
-- ONE FILE PER MONTH, AND THAT IS WHY THIS TABLE IS SHAPED LIKE THIS. The
-- application already cuts the stock into month buckets for the offline
-- devices (includes/bucket.php), each with a fingerprint over count, last
-- change and a checksum. That answer — "did anything change in this month?"
-- — is exactly the question a nightly upload has to ask, and asking it a
-- second way would mean two rules that drift apart. So a row here is a
-- bucket: which month, under which fingerprint it went up, and into which
-- file.
--
-- THE FINGERPRINT ALONE WOULD LIE. It says what the database looked like at
-- the last upload; it says nothing about whether the file is still lying
-- over there. Whoever empties the folder in their storage would never get
-- anything back. So the sweep asks the provider for the folder listing too
-- and treats a missing file like a changed month (pcloud_folder_files).
--
-- NO FOREIGN FINDS. A backup of my data is my data — what comes through
-- containers and excursions belongs to whoever made it and is backed up in
-- THEIR storage. That falls out on its own: the buckets only ever cover
-- map_points of one user.
CREATE TABLE IF NOT EXISTS map_cloud_backups (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    bucket_key   CHAR(7)      NOT NULL COMMENT 'der Monat, "2026-08" — derselbe Schlüssel wie in bucket.php',
    fingerprint  VARCHAR(32)  NOT NULL COMMENT 'der Abdruck, unter dem diese Datei hochgeladen wurde',
    total        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'wie viele Punkte darin standen — für die Anzeige',
    filename     VARCHAR(190) NOT NULL COMMENT 'wie die Datei drüben heißt; darüber wird sie wiedergefunden',
    fileid       BIGINT UNSIGNED NULL COMMENT 'die Kennung beim Anbieter, oder NULL wenn er keine nennt',
    bytes        INT UNSIGNED NULL COMMENT 'was der Anbieter als Größe bestätigt hat',
    uploaded_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    -- Ein Monat, eine Datei, ein Nutzer.
    UNIQUE KEY idx_backup_bucket (user_id, bucket_key),
    CONSTRAINT fk_backup_user FOREIGN KEY (user_id)
        REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The switch, and when the sweep last ran.
--
-- ON BY DEFAULT, and that is a decision. Nothing in this application goes
-- onto a device unasked — but a cloud connection is not a device: it exists
-- because somebody deliberately handed over their own storage, and finds
-- lying there beside the photos is what that storage is for. Whoever does
-- not want it turns it off in the tab, and then nothing more is written.
--
-- THE COLUMNS SIT ON THE CONNECTION, not on the user. Without a connection
-- there is nothing to back up into, and disconnecting is supposed to take
-- the whole arrangement with it — including the switch, so that reconnecting
-- starts from a clean state instead of from a decision made a year ago.
ALTER TABLE map_cloud_accounts
    ADD COLUMN backup_enabled TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '0 = keine Datensicherung in diese Cloud'
        AFTER folder,
    ADD COLUMN backup_at DATETIME NULL
        COMMENT 'wann der letzte Durchgang fertig wurde'
        AFTER backup_enabled;
