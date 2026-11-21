-- Repair for a window of a few hours on 21.08.2026: filter.js still wrote
-- its term rows with the old key 'art' after 0063 had moved the stored
-- vocabulary to 'type' — an autolist saved in that window carries rows the
-- reader answers with "contains" regardless of what was chosen. The same
-- REPLACE as in 0063, idempotent; rows already correct are untouched.
UPDATE map_lists SET filter =
    REPLACE(REPLACE(REPLACE(filter,
        '"art":', '"type":'),
        '"enthaelt_nicht"', '"contains_not"'),
        '"enthaelt"', '"contains"')
    WHERE filter IS NOT NULL;
UPDATE map_containers SET filter =
    REPLACE(REPLACE(REPLACE(filter,
        '"art":', '"type":'),
        '"enthaelt_nicht"', '"contains_not"'),
        '"enthaelt"', '"contains"')
    WHERE filter IS NOT NULL;
