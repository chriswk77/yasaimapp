-- A PLATE AS A PICTURE IN THE CLOUD (13.10.2026).
--
-- „die Tafel als Bild in die Cloud … damit man da schnell eine
-- Vorschau hat und nicht die Tafel jedes Mal neu bauen muss", and „der
-- Dateiname soll so sein, dass sie immer ganz oben steht über den
-- einzelnen Fundbildern".
--
-- ONE ROW PER PAGE OF A PLATE: the browser draws the page as the export
-- does (plateprint.js), the server lays the JPEG into the maker's cloud,
-- into the find's folder, as „<Art Datum Ort> 00 Tafel.jpg" — the „00"
-- sorts before the pictures' „01". The row says where it lies and what it
-- was drawn from (hash), so an unchanged page is never sent twice; a small
-- preview of it is kept on this server (includes/plate_images.php), the
-- page itself only in the cloud. No cloud, no picture.

CREATE TABLE map_plate_images (
    plate_id   INT UNSIGNED     NOT NULL,
    page       TINYINT UNSIGNED NOT NULL COMMENT '1, 2, … as the plate counts its pages',
    provider   VARCHAR(20)      NOT NULL,
    fileid     VARCHAR(512)     NOT NULL,
    folderid   VARCHAR(512)     NOT NULL,
    name       VARCHAR(255)     NOT NULL COMMENT 'the file name in the cloud',
    hash       CHAR(16)         NOT NULL COMMENT 'what the browser drew it from (plates.js pageHash)',
    width      SMALLINT UNSIGNED NOT NULL,
    height     SMALLINT UNSIGNED NOT NULL,
    updated_at DATETIME         NOT NULL,
    PRIMARY KEY (plate_id, page),
    CONSTRAINT fk_plate_images_plate FOREIGN KEY (plate_id) REFERENCES map_plates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
