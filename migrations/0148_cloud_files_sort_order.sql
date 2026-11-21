-- THE CLOUD RECORDINGS GET A PLACE IN THE GALLERY'S ORDER (30.09.2026).
--
-- The gallery of the point window can be arranged by dragging since this
-- afternoon (photos_reorder in includes/photos.php) — and it reached only
-- the voucher photos in map_photos, which carry sort_order since 0031.
-- The maintainer tried it on the desktop and nothing moved: there his pictures
-- come in through „Auswählen" and are cloud recordings, rows of
-- map_cloud_files without any order. They are the main part of a find —
-- „Belegfotos macht man eins, vielleicht zwei. Aber was in der Cloud
-- landet, Sporenbilder, Stacking-Aufnahmen und weitere Detailaufnahmen …
-- da ist es wichtiger."
--
-- ONE NUMBER SPACE PER POINT across both tables: a voucher photo and a
-- micrograph under the same subject name stand in one row and swap places
-- with each other, so their numbers must compare. Ties go to the voucher
-- photo, then to the lower id — the server and the gallery break them the
-- same way.
--
-- THE STOCK IS NUMBERED BEHIND THE PHOTOS, oldest first: every point's
-- recordings start after its highest photo number and follow in the order
-- they were uploaded. Before this migration the gallery showed the photos
-- first and the recordings after them newest first; oldest first is the
-- order a fresh upload keeps from now on (it goes to the end, like a
-- photo), so the stock takes it once here instead of standing reversed
-- beside everything new.
--
-- THE RANKS GO THROUGH A TEMPORARY TABLE, for 0104's reason: an UPDATE
-- may not read its own table in a subquery, and the runner executes the
-- statements on one connection, so the table lives long enough. No window
-- functions — the live database is MariaDB, and COUNT does the same here.
--
-- THE CODE CAN GO LIVE FIRST: until the column exists, the recordings stay
-- unsortable at the end of their group, as before.

ALTER TABLE map_cloud_files
    ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER title;

CREATE TEMPORARY TABLE tmp_cloud_order AS
SELECT f.id,
       COALESCE((SELECT MAX(p.sort_order) + 1 FROM map_photos p WHERE p.point_id = f.point_id), 0)
       + (SELECT COUNT(*) FROM map_cloud_files g
           WHERE g.point_id = f.point_id
             AND (g.uploaded_at < f.uploaded_at OR (g.uploaded_at = f.uploaded_at AND g.id < f.id))) AS place
  FROM map_cloud_files f;

UPDATE map_cloud_files f
  JOIN tmp_cloud_order o ON o.id = f.id
   SET f.sort_order = LEAST(o.place, 65535);

DROP TEMPORARY TABLE tmp_cloud_order;
