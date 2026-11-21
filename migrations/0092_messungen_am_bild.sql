-- The measures learn to persist (stage two of the measuring tool,
-- 28.08.2026: the statistics sidebar — spore thumbnails, the
-- average, the distribution). Until now a measure was view-only and died
-- with the photo switch; a statistic that is meant to reach ACROSS the
-- pictures of one find ("eine Sporenmessung unter Umständen über mehrere
-- Sporenfotos") has to read them back, so they get a column.
--
-- ONE JSON ARRAY PER PICTURE, not a row per measure: the measures are
-- read and written only ever as the whole set of one picture — there is
-- no query that would want a single spore by id, and thirty spores as
-- thirty rows would buy JOINs for nothing. Each entry carries its own
-- REFERENCE WIDTH like the scale does (migration 0090), so a spore
-- measured on the 2048-px preview still lies on the right spot of the
-- 6000-px original.
--
-- Both photo tables at once, same shape, one gate (photo_measures_write):
-- voucher photos and cloud recordings are the same pictures to the tool.
ALTER TABLE map_photos
    ADD COLUMN measures TEXT NULL
        COMMENT 'JSON-Liste der Messungen: {type: line|rect, points, width} — NULL = keine'
        AFTER scale;
ALTER TABLE map_cloud_files
    ADD COLUMN measures TEXT NULL
        COMMENT 'JSON wie map_photos.measures — NULL = keine'
        AFTER scale;
