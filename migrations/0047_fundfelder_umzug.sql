-- The find fields arrive (includes/fundfelder.php) — the old stock moves.
--
-- The fixed field set of every species list brings "Substrat / Wirt" and
-- "Begleitvegetation" with it. Both already existed as root fields of
-- their own, one of them under a longer name ("Substrat /
-- Mykorrhiza-Partner / Wirt"). This migration drops the old definitions
-- (the set replaces them) and renames the stored VALUES to the new field
-- name — otherwise the points would no longer show their substrate
-- entries.
--
-- NO WITH, NO JSON_SET: the live server speaks MariaDB (the lesson from
-- migration 0040). The values lie as JSON TEXT with the escapes that
-- json_encode writes — the slash as \/. REPLACE therefore replaces BOTH
-- spellings, the escaped and the bare one: which is present depends on who
-- last wrote the value, and a REPLACE that finds nothing does nothing. The
-- key name is unwieldy enough that it occurs nowhere else — precisely why
-- it can be replaced as a character string without understanding JSON.
--
-- Affected are all places that carry the field name:
--   map_points.zusatz        the values themselves
--   map_points.zusatz_arten  the name references per field
--   map_points.feldzeiten    "zusatz.<Feldname>" keys
--   map_points.original      ditto (rescue anchor)
--   map_point_arten.feld     plain text, derived table
--   map_list_felder          the old definitions on species lists

UPDATE map_points SET zusatz = REPLACE(zusatz,
    '"Substrat \\/ Mykorrhiza-Partner \\/ Wirt"', '"Substrat \\/ Wirt"')
 WHERE zusatz LIKE '%Mykorrhiza-Partner%';
UPDATE map_points SET zusatz = REPLACE(zusatz,
    '"Substrat / Mykorrhiza-Partner / Wirt"', '"Substrat / Wirt"')
 WHERE zusatz LIKE '%Mykorrhiza-Partner%';

UPDATE map_points SET zusatz_arten = REPLACE(zusatz_arten,
    '"Substrat \\/ Mykorrhiza-Partner \\/ Wirt"', '"Substrat \\/ Wirt"')
 WHERE zusatz_arten LIKE '%Mykorrhiza-Partner%';
UPDATE map_points SET zusatz_arten = REPLACE(zusatz_arten,
    '"Substrat / Mykorrhiza-Partner / Wirt"', '"Substrat / Wirt"')
 WHERE zusatz_arten LIKE '%Mykorrhiza-Partner%';

UPDATE map_points SET feldzeiten = REPLACE(feldzeiten,
    'zusatz.Substrat \\/ Mykorrhiza-Partner \\/ Wirt', 'zusatz.Substrat \\/ Wirt')
 WHERE feldzeiten LIKE '%Mykorrhiza-Partner%';
UPDATE map_points SET feldzeiten = REPLACE(feldzeiten,
    'zusatz.Substrat / Mykorrhiza-Partner / Wirt', 'zusatz.Substrat / Wirt')
 WHERE feldzeiten LIKE '%Mykorrhiza-Partner%';

UPDATE map_points SET original = REPLACE(original,
    'zusatz.Substrat \\/ Mykorrhiza-Partner \\/ Wirt', 'zusatz.Substrat \\/ Wirt')
 WHERE original LIKE '%Mykorrhiza-Partner%';
UPDATE map_points SET original = REPLACE(original,
    'zusatz.Substrat / Mykorrhiza-Partner / Wirt', 'zusatz.Substrat / Wirt')
 WHERE original LIKE '%Mykorrhiza-Partner%';

UPDATE map_point_arten SET feld = 'Substrat / Wirt'
 WHERE feld = 'Substrat / Mykorrhiza-Partner / Wirt';

-- The old definitions fall only on SPECIES LISTS — there the fixed set
-- replaces them. Whoever carries the same names on an ordinary list keeps
-- them: without the species checkmark there are no find fields there.
DELETE f FROM map_list_felder f
  JOIN map_lists l ON l.id = f.list_id
 WHERE l.ist_arten = 1
   AND f.name IN ('Substrat / Mykorrhiza-Partner / Wirt', 'Begleitvegetation');
