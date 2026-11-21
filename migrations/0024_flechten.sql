-- Lichens: a second reference list for Germany beside the DGfM list.
--
-- WHY A SECOND ONE AT ALL
-- The DGfM list carries 39,248 fungus names and among them all of 157 under
-- "Lichenes" — of which two have a German name. And the 157 are mostly not
-- lichens at all but fungi that live ON lichens. That is no omission by the
-- DGfM: lichens are a field of their own with a learned society of their
-- own, and the mycological reference list does not claim them.
--
-- In practice this meant: whoever typed "Cladonia" got seven lichenicolous
-- fungi suggested and not a single Cladonia. The reindeer lichen that
-- stands in every pine forest was not to be found via the Germany list.
-- Via GBIF it was — but GBIF is only available online, and the name is
-- needed in the forest.
--
-- WHERE THE LIST COMES FROM
-- From the Red List of the lichens and lichen-inhabiting fungi of Germany
-- (Wirth et al. 2011), as the Red List Centre of the BfN publishes it as a
-- data table. It is the counterpart of the DGfM list: the species concept
-- authoritative for Germany, with the same threat scale.
--
-- WHY A TABLE OF ITS OWN AND NOT INSIDE map_tax_dgfm
-- Because admin/dgfm_import.php empties the table and refills it. The
-- lichens would lie in the void after the next import of the DGfM list — a
-- mistake nobody would notice until someone searches in the forest.
--
-- GERMAN NAMES STAY EMPTY, and that is no shortcoming of the implementation:
-- the Red List carries none. Unlike with macrofungi, German lichen names
-- are hardly established; GBIF knows one for about every eighth species.
-- The column stands ready in case backfilling them ever pays off.

CREATE TABLE map_tax_flechten (
    taxnr          INT UNSIGNED NOT NULL COMMENT 'laufende Nummer aus der Einspieldatei',
    sipnr          INT UNSIGNED NOT NULL COMMENT 'zeigt auf den gültigen Namen; bei diesem auf sich selbst',
    rang           VARCHAR(20)  NULL,
    binomial       VARCHAR(200) NULL COMMENT 'Name ohne Autor',
    gattung        VARCHAR(100) NULL,
    art            VARCHAR(100) NULL,
    autor          VARCHAR(200) NULL,
    deutscher_name VARCHAR(255) NULL COMMENT 'die Rote Liste führt keine; Spalte für später',
    rote_liste_d   VARCHAR(10)  NULL COMMENT 'Kategorie wie bei den Pilzen: 0 1 2 3 G R V D *',
    gruppe         VARCHAR(40)  NULL COMMENT 'Flechte, lichenicoler Pilz, flechtenaehnlicher Pilz',
    suchtext       VARCHAR(600) NULL COMMENT 'wissenschaftlicher und deutscher Name, für die Volltextsuche',
    PRIMARY KEY (taxnr),
    KEY (sipnr),
    KEY (binomial),
    FULLTEXT KEY ft_suchtext (suchtext)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
