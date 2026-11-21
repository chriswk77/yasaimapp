-- Every picture can carry a NAME (30.08.2026: „dass man die
-- Bilder benennt, also Hauptmotiv, Habitat, Schnittbild, Unterseite …
-- für die Mikrobilder Sporen, Cheilozystiden …"). The title is typed in
-- the photo window's top bar, fed by suggestions the account can extend
-- in Einstellungen → Messen — and it is the GROUNDWORK for renaming the
-- files into a fixed pattern later („dass die Bilder dann automatisch in
-- ein bestimmtes Format umbenannt werden"): the pattern will need a word
-- per picture, and this column is that word.
--
-- Both photo tables at once, same shape, one gate (photo_title_clean):
-- voucher photos and cloud recordings are the same pictures to the tool,
-- exactly as with scale (0090) and measures (0092). NULL means unnamed —
-- the ordinary state of everything shot before today.
ALTER TABLE map_photos
    ADD COLUMN title VARCHAR(120) NULL
        COMMENT 'Bildtitel (Hauptmotiv, Sporen, …) — NULL = unbenannt'
        AFTER camera;
ALTER TABLE map_cloud_files
    ADD COLUMN title VARCHAR(120) NULL
        COMMENT 'wie map_photos.title — NULL = unbenannt'
        AFTER height;
