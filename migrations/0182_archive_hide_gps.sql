-- HIDE THE FIND SPOTS OF A PICTURE ARCHIVE (02.11.2026: „dass
-- man das GPS rausnimmt, so dass man die Fundorte verschleiern kann … man
-- hakt hier diesen Punkt an, und dann geschieht es; wenn nicht angehakt,
-- dann geschieht auch nichts").
--
-- One tick per archive under Verwaltung → Cloud → Bildarchive, off until
-- its owner sets it. Set, his FRIENDS see no coordinates of it any more:
-- none in the file names („[50.10781, 8.21527]"), none in the table's
-- lat/lon, none in the pictures' EXIF or XMP (includes/picture_fence.php,
-- picture_without_gps). The place written in the name („Wiesbaden,
-- Nerotal") stays — he asked for the GPS. The owner himself sees all.

ALTER TABLE map_picture_archives
    ADD COLUMN hide_gps TINYINT(1) NOT NULL DEFAULT 0 AFTER title;
