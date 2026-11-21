-- The DGfM's four lists as marks on the fungus reference
-- (17.09.2026: „Die DGfM hat auf ihrer Seite einige Listen —
-- Verantwortungsarten und drei Listen Speisepilze, uneinheitlich bewertet
-- und Giftpilze. Diese vier Listen würde ich gerne auch ausfiltern.").
--
-- ONE ROW PER LINE OF THE SOURCE, the name as the DGfM wrote it. The lists
-- carry no taxon numbers, so every row records how it was matched against
-- map_tax_dgfm: a species by its binomial (taxnr = the VALID taxon, a
-- synonym's row points at its accepted name), a genus claim („Cortinarius
-- spp.") by the genus alone, or not at all — those rows stay in the table
-- so the administration can show a person what did not fit.
--
-- NOTHING IS WRITTEN TO THE POINT. Like the Red List category, the marks
-- are looked up on the title species at load time (species_dgfm_marks in
-- includes/dgfm_marks.php): a list reissued next spring changes what an
-- old find shows, and that is right — the find did not change, the
-- knowledge did.
--
-- Filled from data/dgfm/marks.csv by the administration (Nachtragen →
-- Arten); the CSV is written by admin/dgfm_lists.mjs from the DGfM's PDFs.
CREATE TABLE map_dgfm_marks (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    list      ENUM('edible', 'inconsistent', 'poisonous', 'responsibility') NOT NULL,
    name      VARCHAR(200) NOT NULL COMMENT 'the name as the source writes it',
    species   VARCHAR(200) NULL COMMENT 'genus and epithet the row names; NULL for a genus claim',
    genus     VARCHAR(100) NULL COMMENT 'the genus a spp. row claims as a whole',
    taxnr     INT UNSIGNED NULL COMMENT 'the valid DGfM taxon the species resolved to',
    matched   ENUM('species', 'genus', 'none') NOT NULL,
    german    VARCHAR(255) NULL,
    value     VARCHAR(20) NULL COMMENT 'responsibility: ! or !!',
    remark    TEXT NULL,
    syndrome  VARCHAR(255) NULL COMMENT 'poisonous: the syndrome column',
    source_date DATE NULL COMMENT 'the „Stand" of the list the row came from',
    PRIMARY KEY (id),
    KEY taxnr (taxnr),
    KEY genus (genus),
    KEY list (list)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
