-- A photo learns its scale (28.08.2026 — stage one of the
-- measuring tool: „mit den Skalen anfangen").
--
-- WHAT THE COLUMN HOLDS: the calibration as JSON — a line in ORIGINAL
-- image pixels and the real length that line spans, in micrometres:
--
--   {"line": [[x1,y1],[x2,y2]], "um": 20000, "corner": "br"}
--
-- Everything else is derived, never stored: µm per pixel follows from
-- line and value, the scale bar's round length from that and the zoom.
-- Storing the SOURCE instead of the factor keeps the calibration
-- re-openable — the line can be shown again, judged again, corrected.
--
-- MICROMETRES AS THE ONE UNIT, whatever the user typed. „2 cm" becomes
-- 20000 µm on the way in; the display picks µm, mm or cm again by
-- magnitude. Two stored units would be a conversion bug waiting for its
-- day.
--
-- ORIGINAL PIXELS, and that is load-bearing: a scale calibrated on a
-- resized copy is silently wrong. map_photos carries width and height,
-- so a reader can verify the line lies within the image it claims to
-- measure.
--
-- 'corner' is where the bar rests (tl/tr/bl/br) — presentation, but per
-- photo and not per viewer: whoever calibrates also decides where the
-- bar least covers the subject.
--
-- ON map_photos AND NOT A TABLE OF ITS OWN: one photo, at most one
-- scale. The measuring data of stage two (spore rectangles) will be
-- many-per-photo and get their own shape when they come.
ALTER TABLE map_photos
    ADD COLUMN scale TEXT NULL
        COMMENT 'JSON: Eichlinie in Originalpixeln, Länge in µm, Balken-Ecke — NULL = nicht geeicht'
        AFTER camera;
