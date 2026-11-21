-- The preset remembers WHICH file in the „Kalibrierung" folder is its
-- calibration image (28.08.2026: re-calibrating „mit dem
-- gleichen Bild natürlich"). Finding it by NAME would be guesswork:
-- renameifexists turns a re-upload into „10x (2).jpg", and the newest of
-- those, not the first, would be the current one. The provider's fileid
-- is exact, survives renames, and pages/calibration_image.php streams
-- the picture back through the server — pCloud links are bound to the
-- asking IP (see pages/cloud_thumb.php), so the browser could not use a
-- link of its own anyway.
--
-- NULL means: no stored image — presets typed in by hand, and those
-- minted before this column. Renaming works for them regardless; only
-- re-measuring needs a freshly chosen picture.
ALTER TABLE map_photo_scales
    ADD COLUMN fileid BIGINT NULL
        COMMENT 'pCloud-Dateikennung des Eichbilds im Ordner Kalibrierung — NULL = keines'
        AFTER height;
