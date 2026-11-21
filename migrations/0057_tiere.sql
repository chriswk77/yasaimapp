-- Tiernamen mit deutschen Bezeichnungen.
--
-- QUELLE: „Taxon list of animals with German names (worldwide)", zusammen-
-- gestellt am Staatlichen Museum für Naturkunde Stuttgart (Holstein &
-- Monje), veröffentlicht über GBIF unter CC-BY 4.0. 18 224 Taxa, davon
-- 12 664 Arten; 12 658 tragen einen deutschen Namen.
--
-- WARUM DIESE UND NICHT DAS GBIF-RÜCKGRAT: die Tiere der Welt sind dort
-- 2,7 Millionen Namen — gerechnet 48 MB über die Leitung und 391 MB
-- Arbeitsspeicher, und damit auf einem Handy nicht tragbar. Diese Liste ist
-- die brauchbare Teilmenge: was einen deutschen Namen hat, ist das, was man
-- im Feld anspricht. Insekten ohne deutschen Namen fehlen dadurch — dafür
-- bräuchte es später eine eigene, auf Europa begrenzte Liste
-- (der Rahmen vom 19.08.2026: Deutschland, Alpen, Europa, weiter
-- nicht).
--
-- Die Form folgt map_tax_flechten und map_tax_pflanzen, damit species.php
-- denselben Zweig darüberlegen kann.
CREATE TABLE map_tax_tiere (
    taxnr          INT UNSIGNED NOT NULL COMMENT 'Nummer aus der taxonID der Quelle',
    sipnr          INT UNSIGNED NOT NULL COMMENT 'zeigt auf den gültigen Namen; bei diesem auf sich selbst',
    rang           VARCHAR(20)  NULL,
    binomial       VARCHAR(200) NULL COMMENT 'Name ohne Autor',
    gattung        VARCHAR(100) NULL,
    art            VARCHAR(100) NULL,
    autor          VARCHAR(200) NULL,
    deutscher_name VARCHAR(255) NULL,
    -- Die Quelle führt keine Gefährdungseinstufung. Die Spalte steht
    -- trotzdem da: species_branch() legt für alle Deutschlandlisten
    -- denselben Zweig darüber und liest rote_liste_d — ohne sie scheitert
    -- die Suche mit „Unknown column", sobald die Tiere dazukommen. Und das
    -- BfN gibt für Tiergruppen sehr wohl Rote Listen heraus; die passen
    -- später hier hinein, ohne dass sich etwas anderes ändert.
    rote_liste_d   VARCHAR(10)  NULL COMMENT 'die Quelle führt keine; Spalte für später',
    gruppe         VARCHAR(60)  NULL COMMENT 'die Klasse aus der Quelle: Insecta, Aves, Mammalia …',
    suchtext       VARCHAR(600) NULL COMMENT 'wissenschaftlicher und deutscher Name, für die Volltextsuche',
    PRIMARY KEY (taxnr),
    KEY (sipnr),
    KEY (binomial),
    KEY (gattung),
    FULLTEXT KEY ft_suchtext (suchtext)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
