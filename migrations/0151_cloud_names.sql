-- THE PICTURES IN THE CLOUD GET THEIR NAMES (04.10.2026).
--
-- „Würden sich die Namen aktuell halten? … wenn ich ein Label
-- ändere, würde sich das Bild dann gleich mit umbenennen. Wenn ich die
-- Reihenfolge ändere, würden die Nummern dann auch bei den Bildern
-- umbenannt." — and: „nur den Ordnern an sich hinten dran die Fundnummer
-- stellen". includes/cloud_names.php derives every picture's name and
-- folder from its find and keeps them current; this brings what it needs.
--
-- THE QUEUE: a find whose names may have changed waits here until the
-- worker has brought its pictures in line (cloud_names_touch, the
-- five-minute bundle, the run right after a request). claimed_at keeps two
-- runs off one find; attempts lets a refusing cloud step back.
--
-- THE NAMES THE ROWS DID NOT KNOW: a voucher photo pushed into the cloud
-- kept only its id there, a co-owner's copy too — the worker learns each
-- name once from the provider and writes it down. And what the camera
-- called a recording, before its first renaming: map_photos already keeps
-- original_name, map_cloud_files gets it, filled from today's names.
--
-- THE SWITCH IS THIS FILE: until it is applied, the folders carry no find
-- number and nothing is renamed (cloud_names_ready). Applied, every find
-- with a picture in a cloud is due at once — the whole stock moves into
-- its find folders and takes its names, in portions, over some hours.

CREATE TABLE map_cloud_names_due (
    point_id   INT UNSIGNED     NOT NULL,
    due_at     DATETIME(6)      NOT NULL COMMENT 'last time something the names are made of changed',
    claimed_at DATETIME         NULL     COMMENT 'a run works on it since',
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (point_id),
    KEY idx_cloud_names_due_order (attempts, due_at),
    CONSTRAINT fk_cloud_names_due_point FOREIGN KEY (point_id) REFERENCES map_points (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE map_photos
    ADD COLUMN cloud_name VARCHAR(255) NULL AFTER cloud_path;

ALTER TABLE map_cloud_copies
    ADD COLUMN name VARCHAR(255) NULL AFTER path;

ALTER TABLE map_cloud_files
    ADD COLUMN original_name VARCHAR(255) NULL AFTER name;

UPDATE map_cloud_files SET original_name = name WHERE original_name IS NULL;

INSERT IGNORE INTO map_cloud_names_due (point_id, due_at)
SELECT point_id, NOW(6) FROM map_cloud_files
UNION
SELECT point_id, NOW(6) FROM map_photos WHERE cloud_fileid IS NOT NULL;
