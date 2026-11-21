-- A bare name refers to the species, not to the aggregate (13.09.2026,
-- the same afternoon as 0103 — „Xerocomellus chrysenteron wird
-- als Rotfußröhrling-Aggregat übersetzt").
--
-- Until today species_reference() resolved a bare binomial by the lowest
-- taxnr, and for the 143 binomials the DGfM list carries twice that was
-- the aggregate row („… agg.", „… Gruppe") as often as not — the maintainer's
-- six Xerocomellus chrysenteron all pointed at the aggregate, and the
-- report printed its German name. Nobody chose that: the suggestion list
-- showed the two rows as one. Since today the list shows both, the name
-- may carry the qualifier, and a bare name goes to the species.
--
-- THE STOCK FOLLOWS THE RULE: every find whose title is the bare binomial
-- (the JSON key) and whose reference is the aggregate row moves to the
-- species row of the same binomial. Every aggregate in the list has
-- exactly one such twin (counted), so nothing here has to guess. On the
-- live stock these are 17 finds of five species. Whoever really means the
-- aggregate now writes „agg." — the field offers it.
--
-- THE PAIRS GO INTO A TEMPORARY TABLE FIRST, and that is not tidiness: as
-- a derived table in the UPDATE, MySQL folded the self-join over 39 000
-- rows into the outer query and ran it once per find — measured 150
-- seconds for 2 900 finds, which is past any shared host's script limit,
-- and an aborted UPDATE rolls back without a trace in the ledger. As a
-- table of 139 rows the same join takes a tenth of a second. The runner
-- executes the statements on one connection, so the table lives long
-- enough.
--
-- REPLACE on the exact pair "<binomial>":"fungi:<taxnr>" — the key is the
-- whole name, so „Armillaria mellea" cannot touch „Armillaria mellea agg."
-- (its key would be the qualified name). touched_at moves, so every
-- device fetches the corrected find (the same reasoning as in
-- title_references_sync).
CREATE TEMPORARY TABLE tmp_aggregate_pairs AS
SELECT a.binomial, a.taxnr AS agg_nr, b.taxnr AS species_nr
  FROM map_tax_dgfm a
  JOIN map_tax_dgfm b
    ON b.binomial = a.binomial AND b.taxnr <> a.taxnr AND b.taxnr = b.sipnr
   AND b.full_name NOT LIKE '% agg.' AND b.full_name NOT LIKE '% Gruppe'
 WHERE a.taxnr = a.sipnr AND (a.full_name LIKE '% agg.' OR a.full_name LIKE '% Gruppe');

UPDATE map_points p
  JOIN tmp_aggregate_pairs x
    ON p.extra_species LIKE CONCAT('%"', x.binomial, '":"fungi:', x.agg_nr, '"%')
   SET p.extra_species = REPLACE(p.extra_species,
           CONCAT('"', x.binomial, '":"fungi:', x.agg_nr, '"'),
           CONCAT('"', x.binomial, '":"fungi:', x.species_nr, '"')),
       p.touched_at = CURRENT_TIMESTAMP(6);

DROP TEMPORARY TABLE tmp_aggregate_pairs;
