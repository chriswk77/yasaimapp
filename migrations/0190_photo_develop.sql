-- THE PICTURE DEVELOPED, NEVER CHANGED (06.11.2026: „ähnlich wie
-- Lightroom … als live gerechneten Layer drüberlegen … nicht das
-- Originalbild editieren … und das Ganze muss natürlich dann auch in die
-- JSON-Datei rein, sodass das Originalbild immer erhalten bleibt").
--
-- The viewer's second mode, „Bearbeiten", keeps its settings here as JSON:
-- the sliders (exposure, contrast, highlights, shadows, temperature, tint,
-- vibrance, saturation, clarity, sharpening) and the ellipses laid over
-- the picture to lighten or darken it, inside or outside. The browser
-- renders them live over the untouched original (assets/js/develop.js);
-- the sidecar beside the picture carries them as well. The server only
-- checks the shape (photo_develop_clean).
--
-- ON BOTH PICTURE TABLES, as scale, measures and media are — „Bilder sind
-- Bilder". TEXT and not VARCHAR(500): a picture with a few ellipses is
-- longer than that. NULL is „nicht bearbeitet", every picture before.

ALTER TABLE map_cloud_files ADD COLUMN develop TEXT NULL AFTER media;
ALTER TABLE map_photos ADD COLUMN develop TEXT NULL AFTER media;
