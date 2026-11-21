-- The cloud recordings learn their ORIGINAL pixel dimensions
-- (28.08.2026): a preset is µm per original pixel, so stamping one used to
-- demand „Volle Größe" first — every single time, although the answer
-- never changes. With the dimensions stored, the chooser can stamp onto
-- the PREVIEW at once: the scale's reference width does the converting,
-- exactly as it always has (migration 0090).
--
-- Filled from two sides, both browser-measured: cloudupload.js reads the
-- picture before it leaves the device (new uploads), and the
-- Volle-Größe button files them the first time an old recording's
-- original is loaded. NULL means: not yet known — the button stays the
-- one way, as before this column.
ALTER TABLE map_cloud_files
    ADD COLUMN width INT NULL
        COMMENT 'Pixelbreite des Originals — NULL = noch nie gemessen'
        AFTER size,
    ADD COLUMN height INT NULL
        COMMENT 'Pixelhöhe des Originals'
        AFTER width;
