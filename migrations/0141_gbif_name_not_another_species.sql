-- GBIF's name only where no other DGfM species keeps it as its own
-- (26.09.2026).
--
-- 0106 gives a DGfM species GBIF's name where no second DGfM taxon falls
-- onto it, counting every species under the GBIF name it WOULD get — not
-- under the DGfM name it keeps when it gets none. So Helvella fallax came
-- as „Helvella atra", GBIF's name for it, while the DGfM's own Helvella
-- atra, the Schwarze Lorchel, kept that very name for want of one of its
-- own: two species behind one suggestion, and the search, which groups by
-- name, showed only one of them. The one such case on the stock, no find
-- carries its name; Helvella fallax is offered under the DGfM's name
-- again.
--
-- The twin of the step after 0106's statement in admin/tax_link.php. A
-- temporary table and not a derived one beside the updated table: the
-- live server speaks MariaDB (see 0040). The fungi package's revision
-- follows after this and 0140 have run live.

CREATE TEMPORARY TABLE tmp_fallback_taken AS
SELECT DISTINCT l.taxnr
  FROM map_tax_links l
  JOIN map_tax_dgfm d ON d.taxnr = l.taxnr
  JOIN map_tax_dgfm o ON o.binomial = l.gbif_name AND o.taxnr = o.sipnr AND o.taxnr <> d.taxnr
  LEFT JOIN map_tax_links lo ON lo.taxnr = o.taxnr
 WHERE l.gbif_name IS NOT NULL AND lo.gbif_name IS NULL;

UPDATE map_tax_links l
  JOIN tmp_fallback_taken b ON b.taxnr = l.taxnr
   SET l.gbif_name = NULL;

DROP TEMPORARY TABLE tmp_fallback_taken;
