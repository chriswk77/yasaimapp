-- The sensu qualifier of the DGfM list goes into the search text
-- (13.09.2026, „Xerocomellus chrysenteron wird als
-- Rotfußröhrling-Aggregat übersetzt — die Taxref-Liste hat einen Eintrag
-- mit sensu stricto. Das müssten wir dem Artenfeld beibringen, dass man
-- das hinter einem Artnamen schreiben kann").
--
-- The list carries 143 binomials twice as valid names — the aggregate
-- („… agg.", „… Gruppe") and the species in the strict sense („… s. str.")
-- — and only full_name tells them apart. Since today the search and the
-- packages deliver the name WITH that qualifier (species_qualifier_sql in
-- includes/species.php, where the reasoning stands), so the two rows are
-- two suggestions. For the qualifier to be TYPED as well, the full-text
-- index has to know it: „+str*" and „+agg*" only hit what stands in
-- search_text. Rebuilt here the way migration 0013 first built it — both
-- names, hyphens as spaces — plus the qualifier.
--
-- THE CASE BELOW IS THE TWIN of species_qualifier_sql(); the import
-- script (admin/dgfm_import.php) uses the PHP one for the same column.
-- Whoever changes a pattern changes both.
UPDATE map_tax_dgfm
   SET search_text = TRIM(CONCAT_WS(' ',
           REPLACE(binomial, '-', ' '),
           REPLACE(COALESCE(german_name, ''), '-', ' '),
           CASE
               WHEN full_name LIKE '% agg.' THEN 'agg.'
               WHEN full_name LIKE '% Gruppe' THEN 'Gruppe'
               WHEN full_name LIKE '% s. str.' OR full_name LIKE '% s.str.' OR full_name LIKE '% s str.' THEN 's. str.'
               WHEN full_name LIKE '% s. l.' OR full_name LIKE '% s.l.' THEN 's. l.'
               WHEN full_name LIKE '% non ss. %' THEN SUBSTRING(full_name, LOCATE(' non ss. ', full_name) + 1)
               WHEN full_name LIKE '% ss. %' THEN SUBSTRING(full_name, LOCATE(' ss. ', full_name) + 1)
           END
       ));
