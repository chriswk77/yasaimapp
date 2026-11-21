-- A FIND'S OTHER FILES, IN THE CLOUD BESIDE ITS PICTURES (05.10.2026).
--
-- Finn asked the maintainer whether he may lay his own files into a find's
-- folder in Google Drive — text notes and the like — and what happens to
-- a picture he renames there. YASAIMAPP sees only what it put into a
-- Drive itself (the drive.file scope), so his files never moved along
-- when a find changed folders. The answer: „im Tab Belege neben
-- dem Untertab Fotos und Sequenzen noch einen Tab … einen Bereich für
-- Ziehen und Droppen … Multi-Upload … Dadurch würden wir ja die
-- Datei-IDs bekommen … bei einem Umzug käme alles mit" — and a file
-- browser with download and preview, folders kept as they were dropped.
--
-- ONE ROW PER FILE, in the cloud of the person who uploaded it, and only
-- that person sees it (his answer to „Wer sieht die Dateien?"). The bytes
-- go straight from the browser to the provider; this server keeps where
-- they lie:
--   home_folderid, home_path  the find's „Dateien" folder — one per find,
--                             person and cloud; the whole folder moves when
--                             the find's folder changes (cloud_names.php)
--   dir                       the folder inside it, as dropped („Stack 1/RAW")
--   folderid                  the folder the file itself lies in
--
-- Not pictures of the gallery: a JPEG laid in here stays a file (his
-- answer to the third question) — which is also why this is a table of
-- its own and not more rows in map_cloud_files, where some ten places
-- take every row for a picture.

CREATE TABLE map_point_files (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    point_id      INT UNSIGNED    NOT NULL,
    provider      VARCHAR(16)     NOT NULL,
    fileid        VARCHAR(512)    NOT NULL,
    folderid      VARCHAR(512)    NULL,
    home_folderid VARCHAR(512)    NOT NULL COMMENT 'the find''s „Dateien" folder at the provider',
    home_path     VARCHAR(512)    NOT NULL COMMENT 'its path inside the app folder',
    dir           VARCHAR(512)    NOT NULL DEFAULT '' COMMENT 'folder inside „Dateien", as dropped',
    name          VARCHAR(255)    NOT NULL,
    size          BIGINT UNSIGNED NULL,
    mime          VARCHAR(100)    NULL,
    uploaded_at   DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_point_files_point (point_id, user_id),
    CONSTRAINT fk_point_files_user  FOREIGN KEY (user_id)  REFERENCES map_users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_point_files_point FOREIGN KEY (point_id) REFERENCES map_points (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
