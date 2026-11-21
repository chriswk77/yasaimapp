-- The stored tile measurements kept their German inner keys through 0063:
-- map_areas.measurements holds {"Luftbild|18":{"jeAnfrage":…,"messbar":…,
-- "leer":…}}. No column carries those names, so the schema move never saw
-- them — but map.js reads measure.perRequest, which was undefined, and
-- "n * undefined" put "mindestens NaN GB" into the download window's
-- estimate (found 22.08.2026 while setting the zoom default to 19).
--
-- Rows written after the move already speak English; REPLACE leaves those
-- untouched, so this is idempotent and safe to run twice.
UPDATE map_areas SET measurements =
    REPLACE(REPLACE(REPLACE(measurements,
        '"jeAnfrage":', '"perRequest":'),
        '"messbar":',   '"measurable":'),
        '"leer":',      '"empty":')
    WHERE measurements IS NOT NULL;
