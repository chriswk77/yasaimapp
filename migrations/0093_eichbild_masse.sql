-- The presets learn the dimensions of their calibration image
-- (28.08.2026): a preset is µm per pixel OF ONE CAMERA at ONE resolution —
-- apply it to a picture with a different pixel width and the numbers are
-- silently wrong by the ratio. With the calibration image's dimensions
-- stored, the viewer can compare widths when stamping and WARN instead of
-- lying: „das Bild ist ja dann mit der gleichen Kamera gemacht" — same
-- camera means same pixel grid, and a deviation means it was not.
--
-- NULL stays legitimate for ever: presets typed in by hand (a factor known
-- from the microscope's papers) have no image and therefore nothing to
-- warn against.
ALTER TABLE map_photo_scales
    ADD COLUMN width INT NULL
        COMMENT 'Pixelbreite des Eichbilds — NULL = ohne Bild geeicht'
        AFTER um_per_px,
    ADD COLUMN height INT NULL
        COMMENT 'Pixelhöhe des Eichbilds'
        AFTER width;
