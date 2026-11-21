-- ROOM FOR MANY MENDED PLACES (11.11.2026: „Ich bin eben an
-- die Grenze der Ausbesserung gestoßen … mehr Stellen lassen sich in
-- diesem Bild nicht ausbessern" — many small single dabs, hardly a long
-- stroke; „wir sollten das etwas erhöhen, alles").
--
-- A picture's edit (`develop`, migration 0190) was TEXT, 64 KB, and the
-- caps were set so that the worst case fitted: 100 places, 2000 points
-- for all strokes together, 200 pieces of „Entfernen". They go up tenfold
-- (assets/js/develop.js SPOTS_MAX, PATH_POINTS_MAX, AI_PIECES_MAX and
-- their twins in includes/photos.php), and the worst case is then some
-- half a megabyte — MEDIUMTEXT, as `develop_versions` already is (0192).
--
-- THE CODE GOES LIVE FIRST. Until this has run, an edit that would not
-- fit the old column is refused with a word that names this migration
-- (photo_develop_fits) — never cut off in the middle of its JSON.

ALTER TABLE map_cloud_files MODIFY COLUMN develop MEDIUMTEXT NULL;
ALTER TABLE map_photos MODIFY COLUMN develop MEDIUMTEXT NULL;
