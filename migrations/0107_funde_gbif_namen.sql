-- The finds take GBIF's name too (13.09.2026, „Kannst Du auch
-- eine Migration schreiben, die die jetzt alten Namen nachträgt in die
-- GBIF-Variante?").
--
-- Every find whose title is the DGfM name of the taxon it references — the
-- name the suggestion list handed it before today — and whose taxon now
-- carries a gbif_name (0106) takes that name as its title. The JSON key in
-- extra_species is the title, so it moves along in the same statement;
-- the reference itself stays the DGfM number, and with it German name and
-- Red List category. On the live stock these are 35 finds under 23 names,
-- Boletus torosus → Imperator torosus among them.
--
-- WHAT STAYS: a find typed by hand under a name that is not its taxon's
-- DGfM binomial (a synonym, a spelling) — it was not handed that name by
-- the list, and a rule that renames what somebody wrote is a different
-- rule. touched_at moves, so every device fetches the renamed find;
-- updated_at does not — a nomenclature update is not the user's edit.
--
-- The derived species rows (map_point_species) keep the old genus until
-- „Arten aus allen Punkten neu ableiten" runs once in the administration.
UPDATE map_points p
  JOIN map_tax_dgfm d ON d.binomial = p.title AND d.taxnr = d.sipnr
  JOIN map_tax_links l ON l.taxnr = d.taxnr AND l.gbif_name IS NOT NULL
   SET p.title = l.gbif_name,
       p.extra_species = REPLACE(p.extra_species,
           CONCAT('"', d.binomial, '":"fungi:', d.taxnr, '"'),
           CONCAT('"', l.gbif_name, '":"fungi:', d.taxnr, '"')),
       p.touched_at = CURRENT_TIMESTAMP(6)
 WHERE p.extra_species LIKE CONCAT('%"', d.binomial, '":"fungi:', d.taxnr, '"%');

-- AND THE FINDS WITHOUT ANY REFERENCE — the old stock from before the
-- references existed (two Boletus torosus of 2024 on the live stock).
-- Their title alone names the taxon, and where that name is ONE valid
-- DGfM entry with a gbif_name, the same rename applies; a name the list
-- carries twice (the aggregates) stays, it has no gbif_name anyway.
UPDATE map_points p
  JOIN map_tax_dgfm d ON d.binomial = p.title AND d.taxnr = d.sipnr
  JOIN map_tax_links l ON l.taxnr = d.taxnr AND l.gbif_name IS NOT NULL
   SET p.title = l.gbif_name,
       p.touched_at = CURRENT_TIMESTAMP(6)
 WHERE (p.extra_species IS NULL OR p.extra_species NOT LIKE '%"fungi:%')
   AND (SELECT COUNT(*) FROM map_tax_dgfm d2 WHERE d2.binomial = d.binomial AND d2.taxnr = d2.sipnr) = 1;
