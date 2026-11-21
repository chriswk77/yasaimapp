-- The name GBIF calls a fungus today, beside the DGfM's (13.09.2026,
-- „Ich habe mich umentschieden. Macht doch die GBIF-Namen. Sie
-- sind aktueller und weltweit hört man darauf" — an hour after deciding
-- the other way, and with a Boletus torosus in hand that GBIF has called
-- Imperator torosus since 2015).
--
-- WHAT THE COLUMN HOLDS: for a valid DGfM entry, the canonical name of the
-- GBIF species its link points at (map_tax_links.gbif_species_key — the
-- same key the cloud folders have gone by since 17.08.2026: „we go by
-- GBIF's species concept"), and ONLY where that is an honest replacement:
--
--   * a different name — NULL means „GBIF says the same", and every reader
--     falls back to the DGfM binomial;
--   * the author does not contradict the match (author_matches is not 0);
--   * an accepted species-rank name at GBIF, not a synonym, not a genus;
--   * NO SECOND DGfM TAXON FALLS ONTO IT. GBIF lumps where the DGfM
--     splits — Agaricus fissuratus into A. arvensis, varieties into their
--     species — and 1 092 of the 21 524 linkable names would collide.
--     Renamed, two DGfM taxa would become one suggestion, and one of them
--     would be gone with its German name. Those keep their DGfM name.
--
-- Not COL's valid_name beside it: that column mixes COL and GBIF, and COL
-- turned Cordyceps militaris into Drechmeria gunnii — a homonym's fate,
-- not the Puppenkernkeule's. GBIF's own accepted species says Cordyceps
-- militaris, and GBIF is what the maintainer asked for.
--
-- 2 133 names on the stock. admin/tax_link.php fills the column the same
-- way after every re-link — the statement there is this one's twin.
ALTER TABLE map_tax_links
    ADD COLUMN gbif_name VARCHAR(255) NULL COMMENT 'GBIFs heutiger Artname, wo er vom DGfM-Namen abweicht und eindeutig ist' AFTER valid_name;

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
