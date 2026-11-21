-- THE FIND'S PLACE AND NAME INSIDE ITS PICTURES (05.10.2026).
--
-- After Finn asked how a picture can be told to belong to a
-- find: „meine Kamera kann zum Beispiel kein GPS … ich ordne das Bild
-- einem Fund zu, dann könnten ja auch die Funddaten GPS mit in die
-- Bilddaten genommen werden. Damit hat man schon mal alles in einer
-- Datei." — „Fundnummer nein, aber GPS und Name ja. Sobald der Name auf
-- sicher steht." includes/picture_stamps.php writes the find's
-- coordinates (and height) into every picture of it, and its name once
-- the find's „Bestimmungssicherheit" says one of the three „sicher".
--
-- THE STAMP SAYS WHAT A PICTURE CARRIES: version, place, height and name
-- as one line („v1|50.123456|9.654321|245.0|Boletus edulis"). The worker
-- compares it with what the find wants now and writes only where the two
-- differ — a new picture, a name that became certain, a find moved. The
-- line keeps the name it wrote, so a later name can take the old one out
-- of the picture's keywords. „skip|…" marks a file it cannot write (not a
-- JPEG, too large, gone), „fail|…" one that failed and is tried again the
-- next day.
--
-- stamped_at is when the worker last looked: a find changed after that
-- (map_points.updated_at) is looked at again. NULL everywhere at first —
-- the whole stock is stamped in portions once this is applied; until then
-- the worker does nothing (picture_stamps_ready).

ALTER TABLE map_photos
    ADD COLUMN stamp      VARCHAR(400) NULL COMMENT 'what the picture file carries of its find (picture_stamps.php)',
    ADD COLUMN stamped_at DATETIME     NULL;

ALTER TABLE map_cloud_files
    ADD COLUMN stamp      VARCHAR(400) NULL COMMENT 'what the picture file carries of its find (picture_stamps.php)',
    ADD COLUMN stamped_at DATETIME     NULL;

ALTER TABLE map_cloud_copies
    ADD COLUMN stamp      VARCHAR(400) NULL COMMENT 'what the copy carries of its find (picture_stamps.php)',
    ADD COLUMN stamped_at DATETIME     NULL;
