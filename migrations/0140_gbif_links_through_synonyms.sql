-- A DGfM name without a GBIF name of its own borrows the link of a
-- homotypic name, or of the species all its names agree on — never of
-- whichever came first (26.09.2026).
--
-- admin/tax_link.php links a DGfM name that GBIF does not know through
-- another name of its group, and took the one with the LOWEST GBIF key.
-- As long as all names of a group lead to one GBIF species that decides
-- nothing (144 of the 175 valid species linked this way); where they do
-- not, chance picked the species, and since 0106 the map offered it by
-- name: the DGfM's Leucocoprinus leucothites, the Rosablättriger
-- Egerlingsschirmling, came as Leucoagaricus subcretaceus — a species of
-- its own at GBIF, and one of its DGfM synonyms. Thelephora fuliginea came
-- as Botryobasidium ramosissimum, another family. The pass also ran before
-- the author step, and lent keys their own rows gave up a moment later:
-- Fomitopsis quercina kept GBIF's separate „Trametes quercina".
--
-- THE RULE, the twin of the pass in admin/tax_link.php, which says it at
-- length: first a name of the group with the same final epithet (hyphens
-- aside) — a homotypic name, the same type in another genus; if none, the
-- GBIF species every other name of the group leads to; if they disagree,
-- no link, and the DGfM's own name stands. Only the links a name made
-- itself (by name, or as GBIF's synonym) lend, after the author step.
--
-- Measured on the local copy of 26.09.2026: 445 such links, 308 keep their
-- key, 72 take another, 65 lose theirs. 21 shown names change: 13 species
-- that came under the name of another species get their own GBIF name or
-- keep the DGfM's (Leucocoprinus leucothites → Leucoagaricus leucothites,
-- Fomitopsis quercina → Daedalea quercina, Thelephora mairei → Tomentella
-- mairei …); 5 whose synonyms disagree show the DGfM's name again
-- (Thelephora eulateritia no longer as Tomentella subvinosa); Thelephora
-- lapida keeps its DGfM name because Thelephora fuliginea now falls on the
-- same GBIF species (0106: no two DGfM taxa under one name); Paranthracobia
-- rehmii and Thelephora punicea get GBIF's name, freed of a collision. No
-- find changes the GBIF species it refers to, so no cloud folder moves;
-- the maintainer's two „Leucoagaricus leucothites" become current names again.
--
-- GBIF's names are then settled anew for the whole table with 0106's rule
-- — the collisions depend on every link at once. The fungi package carries
-- the shown names: its revision follows in a second push, after this has
-- run live (a device that fetched the new revision first would keep the
-- old names).
--
-- Plain SQL only, temporary tables instead of window functions: the live
-- server speaks MariaDB of unknown vintage (see 0040).

CREATE TEMPORARY TABLE tmp_synonym_links AS
SELECT l.taxnr,
       CASE WHEN c.homotypic_species = 1 THEN c.homotypic_key
            WHEN c.homotypic_species = 0 AND c.species = 1 THEN c.any_key
       END AS gbif_key
  FROM map_tax_links l
  LEFT JOIN (
        SELECT d.taxnr,
               COUNT(DISTINCT CASE WHEN REPLACE(SUBSTRING_INDEX(s.binomial, ' ', -1), '-', '')
                                      = REPLACE(SUBSTRING_INDEX(d.binomial, ' ', -1), '-', '')
                                   THEN v.gbif_species_key END) AS homotypic_species,
               MIN(CASE WHEN REPLACE(SUBSTRING_INDEX(s.binomial, ' ', -1), '-', '')
                           = REPLACE(SUBSTRING_INDEX(d.binomial, ' ', -1), '-', '')
                        THEN v.gbif_key END) AS homotypic_key,
               COUNT(DISTINCT v.gbif_species_key) AS species,
               MIN(v.gbif_key) AS any_key
          FROM map_tax_dgfm d
          JOIN map_tax_dgfm s ON s.sipnr = d.sipnr AND s.taxnr <> d.taxnr
          JOIN map_tax_links v ON v.taxnr = s.taxnr AND v.gbif_key IS NOT NULL
               AND v.gbif_via IN ('name', 'gbif_synonym')
         WHERE d.taxnr IN (SELECT taxnr FROM map_tax_links WHERE gbif_via = 'dgfm_synonym')
         GROUP BY d.taxnr
       ) c ON c.taxnr = l.taxnr
 WHERE l.gbif_via = 'dgfm_synonym';

-- The species key comes with the key, as the passes by name take it. No
-- key means no link: the row stays for whatever COL holds in it.
UPDATE map_tax_links l
  JOIN tmp_synonym_links t ON t.taxnr = l.taxnr
  LEFT JOIN map_tax_gbif g ON g.gbif_key = t.gbif_key
   SET l.gbif_key         = g.gbif_key,
       l.gbif_species_key = g.species_key,
       l.gbif_via         = IF(g.gbif_key IS NULL, NULL, 'dgfm_synonym'),
       l.gbif_candidates  = IF(g.gbif_key IS NULL, NULL, 1);

-- The valid name the script's report reads, for the rows that changed —
-- its statement „Gültigen Namen bestimmen", COL before GBIF.
UPDATE map_tax_links v
  JOIN tmp_synonym_links t ON t.taxnr = v.taxnr
  LEFT JOIN map_tax_col  c ON c.col_id   = v.col_species_id
  LEFT JOIN map_tax_gbif g ON g.gbif_key = v.gbif_species_key
  JOIN map_tax_dgfm      d ON d.taxnr    = v.taxnr
  LEFT JOIN map_tax_dgfm a ON a.taxnr    = d.sipnr
   SET v.valid_name = COALESCE(c.canonical, g.canonical, a.binomial, d.binomial),
       v.valid_source = CASE
           WHEN c.canonical IS NOT NULL THEN 'col'
           WHEN g.canonical IS NOT NULL THEN 'gbif'
           ELSE 'dgfm' END;

DROP TEMPORARY TABLE tmp_synonym_links;

-- GBIF's names anew, 0106's statement word for word.
UPDATE map_tax_links SET gbif_name = NULL WHERE gbif_name IS NOT NULL;

CREATE TEMPORARY TABLE tmp_shown AS
SELECT COALESCE(g.canonical, d.binomial) AS shown, COUNT(*) AS n
  FROM map_tax_dgfm d
  LEFT JOIN map_tax_links l ON l.taxnr = d.taxnr AND (l.author_matches IS NULL OR l.author_matches = 1)
  LEFT JOIN map_tax_gbif g ON g.gbif_key = l.gbif_species_key AND g.is_synonym = 0
       AND g.taxon_rank IN ('SPECIES', 'SUBSPECIES', 'VARIETY', 'FORM')
 WHERE d.taxnr = d.sipnr
 GROUP BY shown;

UPDATE map_tax_links l
  JOIN map_tax_dgfm d ON d.taxnr = l.taxnr AND d.taxnr = d.sipnr
  JOIN map_tax_gbif g ON g.gbif_key = l.gbif_species_key AND g.is_synonym = 0
       AND g.taxon_rank IN ('SPECIES', 'SUBSPECIES', 'VARIETY', 'FORM')
  JOIN tmp_shown s ON s.shown = g.canonical AND s.n = 1
   SET l.gbif_name = g.canonical
 WHERE (l.author_matches IS NULL OR l.author_matches = 1)
   AND g.canonical <> d.binomial;

DROP TEMPORARY TABLE tmp_shown;
