-- Die Tiere Europas — wissenschaftliche Namen, ohne deutsche.
--
-- QUELLE: Fauna Europaea (de Jong Y, 2016, Fauna Europaea Consortium),
-- DOI 10.15468/ymk1bx, CC-BY-SA 4.0. Die Lizenz steht nicht bei GBIF —
-- dort ist das Feld leer —, sondern in der eml.xml des Archivs selbst.
-- Nachgelesen am 19.08.2026, weil zwei Sekundärquellen CC-BY behaupteten:
-- „This work is licensed under a Creative Commons Attribution-ShareAlike
-- 4.0 International (CC BY-SA 4.0) License."
--
-- SHARE-ALIKE HEISST: das daraus gebaute Namenspaket ist eine Bearbeitung
-- und wird selbst unter CC-BY-SA ausgeliefert. Der übrige Code bleibt davon
-- unberührt — SA greift auf das bearbeitete Material, nicht auf das
-- Programm, das es ausliefert.
--
-- WARUM DIESE LISTE: die Stuttgarter Tierliste (map_tax_tiere) ist ein
-- weltweiter Vogelbestand mit Beigaben — 8 808 Vögel, aber nur 2 001
-- Insekten und 59 Spinnentiere. Hier sind es 169 903 Insekten- und
-- 20 697 Spinnentierarten Europas. Die beiden ergänzen sich: dort die
-- deutschen Namen, hier die Vollständigkeit.
--
-- 291 428 Zeilen, davon 230 538 Arten und 25 912 Unterarten.
CREATE TABLE map_tax_faeu (
    taxnr          INT UNSIGNED NOT NULL COMMENT 'taxonID der Quelle',
    sipnr          INT UNSIGNED NOT NULL COMMENT 'zeigt auf den gültigen Namen; bei diesem auf sich selbst',
    rang           VARCHAR(20)  NULL,
    binomial       VARCHAR(200) NULL COMMENT 'Name ohne Autor, mit Unterart wenn vorhanden',
    gattung        VARCHAR(100) NULL,
    art            VARCHAR(100) NULL,
    autor          VARCHAR(200) NULL,
    -- Die Quelle führt keine deutschen Namen und keine Gefährdung. Beide
    -- Spalten stehen da, weil species_branch() für alle Deutschlandlisten
    -- denselben Zweig darüberlegt und sie liest; ohne sie scheitert die
    -- Suche mit „Unknown column". Deutsche Namen kommen aus map_tax_tiere.
    deutscher_name VARCHAR(255) NULL,
    rote_liste_d   VARCHAR(10)  NULL,
    gruppe         VARCHAR(60)  NULL COMMENT 'die Klasse, aus der Elternkette gewonnen',
    suchtext       VARCHAR(600) NULL,
    PRIMARY KEY (taxnr),
    KEY (sipnr),
    KEY (binomial),
    KEY (gattung),
    FULLTEXT KEY ft_suchtext (suchtext)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
