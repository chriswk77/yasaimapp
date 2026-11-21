-- The cloud recordings learn their scale too (28.08.2026:
-- „Das sind aber gerade die Bilder, die ich ja bearbeiten will") — the
-- full-resolution micrographs and stacked shots in the user's own
-- storage, exactly the pictures the measuring tool exists for. Stage one
-- had left them out on the grounds that the viewer shows their PREVIEW
-- and preview pixels lie about the original.
--
-- WHAT DISSOLVED THAT OBJECTION: the calibration now carries its
-- REFERENCE WIDTH — the pixel width of the image it was drawn on. With
-- that, every showing converts exactly: a line drawn on a 2048-px
-- preview and one drawn on the 6000-px original mean the same
-- micrometres, because width travels with the line. The same field is
-- written for ordinary photos from now on; where it is absent (the
-- calibrations of the first day), the displayed image's own width holds,
-- which for map_photos is the same number.
--
-- Same column, same JSON shape as map_photos.scale (migration 0088) —
-- one gate, photo_scale_write, validates both.
ALTER TABLE map_cloud_files
    ADD COLUMN scale TEXT NULL
        COMMENT 'JSON wie map_photos.scale: Eichlinie, Länge in µm, Ecke, Bezugsbreite — NULL = nicht geeicht'
        AFTER size;
