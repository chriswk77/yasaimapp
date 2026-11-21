-- WHO SEES ONE'S PICTURE ARCHIVES, WITH THEIR FIND SPOTS OR WITHOUT, AND
-- WHAT STAYS HIDDEN (02.11.2026). Until now every friend saw
-- every archive, and „Fundorte verbergen" (0182) was one tick per archive
-- for all friends at once. The maintainer wanted a choice among his friends
-- („wem gebe ich meine Archivbilder wirklich frei") and a filter per
-- friend — then cut the filter to what matters: the coordinates, and
-- genera and species one does not show yet („Doktoranden … wollen ihre
-- aktuellen Funde und Mikroskopfotos nicht unbedingt teilen, bevor sie
-- nicht die Veröffentlichung durchhaben"). Edible fungi and the Red List
-- were dropped as filters: whose pictures of a Steinpilz one sees „ist
-- egal", where it grew is not, and that is the spots tick.
--
-- PER FRIEND, FOR ALL THE OWNER'S ARCHIVES — a filter on genera and
-- species cuts across folders, and two folders do not need two answers.
--
-- A ROW ONLY WHERE THE OWNER DECIDED. A friend without a row gets the
-- owner's answer for „Neue Freunde", which is the row whose viewer is the
-- owner himself (he needs none for himself — he always sees everything).
-- So a friendship made tomorrow follows it without anybody writing a row.
-- Whoever changes that answer first gets a row for every friend who has
-- none, carrying the old one (archive_audience_set), so changing it never
-- reaches back to the friends one already has.
--
-- 0182's hide_gps is carried over as that „Neue Freunde" answer: whoever
-- ticked it on any archive hid his spots from all friends. The column
-- stays — the code before this migration reads it — but nothing reads it
-- afterwards.

CREATE TABLE map_archive_viewers (
    owner_id         INT UNSIGNED NOT NULL COMMENT 'whose archives',
    viewer_id        INT UNSIGNED NOT NULL COMMENT 'the friend this answer is for; the owner himself = „Neue Freunde"',
    sees             TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'sees the pictures at all',
    spots            TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'with coordinates — in the file names, the data and the pictures',
    hidden           TEXT             NULL COMMENT 'JSON {genera: [], species: []} — not shown to him',
    PRIMARY KEY (owner_id, viewer_id),
    KEY idx_archive_viewers_viewer (viewer_id),
    CONSTRAINT fk_archive_viewers_owner FOREIGN KEY (owner_id) REFERENCES map_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_archive_viewers_viewer FOREIGN KEY (viewer_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO map_archive_viewers (owner_id, viewer_id, sees, spots)
SELECT DISTINCT user_id, user_id, 1, 0 FROM map_picture_archives WHERE hide_gps = 1;
