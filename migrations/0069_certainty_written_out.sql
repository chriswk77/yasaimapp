-- The certainty steps written out: "mak. sicher" / "mik. sicher" become
-- "makroskopisch sicher" / "mikroskopisch sicher" (23.08.2026)
-- — in the dropdown AND in what points already carry.
--
-- THE OPTIONS OF A CHOICE FIELD ARE STORED VALUES, not labels: the field
-- keeps the chosen string in map_points.extra, the sharing keeps it a
-- second time in the original snapshot, and an overwritten change keeps
-- it a third time in map_point_overwritten. Renaming only the option list
-- would leave every stored value orphaned — the dropdown would show it as
-- a stray extra entry forever, and the new filter checkboxes (drawn from
-- the option list) would never match it.
--
-- REPLACE ON THE QUOTED VALUE, with the JSON quotes as anchors: the exact
-- string '"mak. sicher"' can only be a complete stored value, never part
-- of a longer text. The underscore in the WHERE matches mak and mik in
-- one pattern.
--
-- touched_at MOVES ALONG, deliberately: the bucket fingerprints hash it
-- (bucket_status in includes/bucket.php), and only a changed fingerprint
-- makes the devices refetch these points. Without the bump every phone
-- would keep the old wording in its store until the point happened to be
-- edited — and the filter would quietly miss it there. The read side
-- still tolerates the old words (identification.js) for exactly the
-- window between code and migration, and for devices that have not
-- resynced yet.
UPDATE map_points
   SET extra = REPLACE(REPLACE(extra,
                   '"mak. sicher"', '"makroskopisch sicher"'),
                   '"mik. sicher"', '"mikroskopisch sicher"'),
       original = REPLACE(REPLACE(original,
                   '"mak. sicher"', '"makroskopisch sicher"'),
                   '"mik. sicher"', '"mikroskopisch sicher"'),
       touched_at = NOW()
 WHERE extra LIKE '%"m_k. sicher"%' OR original LIKE '%"m_k. sicher"%';

UPDATE map_point_overwritten
   SET value = REPLACE(REPLACE(value,
                   '"mak. sicher"', '"makroskopisch sicher"'),
                   '"mik. sicher"', '"mikroskopisch sicher"')
 WHERE value LIKE '%"m_k. sicher"%';
