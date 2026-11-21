-- Plants: the third reference list for Germany.
--
-- WHAT PLANTS ARE FOR IN A FUNGUS MAP
-- Because many fungi cannot be identified without their plant. A rust
-- fungus is kept under its host, a mycorrhizal fungus under its partner,
-- and whoever wants to know why waxcaps stand on one poor meadow and not
-- on the next notes down the plant community as well. The find site is not
-- just a coordinate.
--
-- WHERE FROM
-- Red List of the ferns and flowering plants of Germany (Metzing et al.
-- 2018), Red List Centre of the BfN — the same origin and the same archive
-- format as the lichen list, which is why the same import script reads it.
--
-- HERE THE GERMAN NAMES ARE PRESENT, and almost without gaps: 5,051 of
-- 5,312 taxa, i.e. 95 %. With the lichens it was two out of 157. That is
-- no coincidence but the difference between a group everyone knows and one
-- hardly anyone names — and it is the reason why searching by German names
-- will be the rule for plants and not the exception.
--
-- status joins because for plants it makes a difference whether something
-- grows here on its own: I native, N neophyte, U casual. 503 of the 5,312
-- are neophytes. The lichen table gets the column along with it, so that
-- both lists keep the same cut — the same shape means one import script
-- instead of two.

ALTER TABLE map_tax_flechten
    ADD status VARCHAR(10) NULL COMMENT 'I einheimisch, N Neobiota, U unbeständig' AFTER rote_liste_d;

CREATE TABLE map_tax_pflanzen (
    taxnr          INT UNSIGNED NOT NULL COMMENT 'laufende Nummer aus der Einspieldatei',
    sipnr          INT UNSIGNED NOT NULL COMMENT 'zeigt auf den gültigen Namen; bei diesem auf sich selbst',
    rang           VARCHAR(20)  NULL,
    binomial       VARCHAR(200) NULL COMMENT 'Name ohne Autor',
    gattung        VARCHAR(100) NULL,
    art            VARCHAR(100) NULL,
    autor          VARCHAR(200) NULL,
    deutscher_name VARCHAR(255) NULL,
    rote_liste_d   VARCHAR(10)  NULL COMMENT 'Kategorie wie bei den Pilzen: 0 1 2 3 G R V D * nb',
    status         VARCHAR(10)  NULL COMMENT 'I einheimisch, N Neobiota, U unbeständig',
    gruppe         VARCHAR(40)  NULL,
    suchtext       VARCHAR(600) NULL COMMENT 'wissenschaftlicher und deutscher Name, für die Volltextsuche',
    PRIMARY KEY (taxnr),
    KEY (sipnr),
    KEY (binomial),
    FULLTEXT KEY ft_suchtext (suchtext)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
