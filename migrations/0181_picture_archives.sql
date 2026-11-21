-- PICTURE ARCHIVES — everybody's older pictures (01.11.2026).
--
-- „Ältere Bilder" began as the maintainer's own pCloud folder, named in the live
-- config.php (includes/old_pictures.php). The same day he asked for it as a
-- module for everyone: „dass man das bei den Einstellungen irgendwo machen
-- kann, einen Pfad freigeben … im Hintergrund auf dem Server wird dann
-- dieser Pfad gepasst … mit Cronjobs geschaut, ob sich was geändert hat".
-- His calls: anybody may attach one; its pictures are seen by the owner and
-- the owner's friends, nobody else; they stay a gallery, not finds.
--
-- WHY A SHARE LINK AND NOT THE CONNECTED CLOUD: every connection is fenced
-- to an app folder (Drive's drive.file, pCloud's private app, OneDrive's
-- Apps folder) and cannot see a library that lay there before. The link is
-- the key to every picture behind it, so it is stored ENCRYPTED, like the
-- cloud tokens (cloud_encrypt), and never handed back to a page.
--
-- map_archive_pictures is only a READING of the folder: every scan replaces
-- an archive's rows whole. map_archive_names is the owner's own word on
-- names the reading could not place — a slip („Mycena scripicola"), a
-- private label, a folder that holds no species. One entry serves every
-- archive of the owner and survives every scan; species '' means: leave it
-- out.

CREATE TABLE map_picture_archives (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED NOT NULL COMMENT 'the owner — he and his friends see the pictures',
    provider         VARCHAR(16)  NOT NULL COMMENT 'pcloud for now',
    link             TEXT         NOT NULL COMMENT 'the share link, encrypted (cloud_encrypt)',
    title            VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'the shared folder''s name, as the provider says it',
    status           VARCHAR(16)  NOT NULL DEFAULT 'new' COMMENT 'new, ready, failed',
    error            VARCHAR(500)     NULL COMMENT 'why the last scan failed',
    pictures         INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'pictures placed under a species',
    species          INT UNSIGNED NOT NULL DEFAULT 0,
    unplaced         INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'pictures under no species',
    unresolved       MEDIUMTEXT       NULL COMMENT 'JSON: the names and folders the reading could not place',
    scanned_at       DATETIME         NULL,
    created_at       DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_picture_archives_user (user_id),
    CONSTRAINT fk_picture_archives_user FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE map_archive_pictures (
    archive_id       INT UNSIGNED NOT NULL,
    file_id          VARCHAR(64)  NOT NULL COMMENT 'the provider''s id of the file',
    name             VARCHAR(255) NOT NULL COMMENT 'the species, lower case, as the folder or file names it',
    find_key         CHAR(40)     NOT NULL COMMENT 'sha1 of what makes the pictures one find',
    find_date        DATE             NULL,
    place            VARCHAR(255) NOT NULL DEFAULT '',
    lat              DECIMAL(9,6)     NULL,
    lon              DECIMAL(9,6)     NULL,
    file             VARCHAR(500) NOT NULL COMMENT 'file name without extension, for the order and the caption',
    label            VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'what the picture shows, where the name says it',
    PRIMARY KEY (archive_id, file_id),
    KEY idx_archive_pictures_name (name),
    CONSTRAINT fk_archive_pictures_archive FOREIGN KEY (archive_id) REFERENCES map_picture_archives (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE map_archive_names (
    user_id          INT UNSIGNED NOT NULL,
    written          VARCHAR(255) NOT NULL COMMENT 'the name or folder as it stands in the archive, lower case',
    species          VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'what it means; empty = leave it out',
    PRIMARY KEY (user_id, written),
    CONSTRAINT fk_archive_names_user FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
