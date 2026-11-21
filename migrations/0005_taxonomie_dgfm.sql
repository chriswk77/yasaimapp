-- German reference list (DGfM) and its link to GBIF.
--
-- Two lists, two purposes. GBIF is worldwide and supplies the big synonymy;
-- the DGfM list is tailored to Germany and brings what GBIF does not have:
-- German names, red-list classifications and a species concept that the
-- local mapping follows. Where the two contradict each other, for a find in
-- Germany the DGfM applies.
--
-- The synonymy works here via two columns: taxnr is the entry, sipnr the
-- valid name. If both are equal, the name is accepted. The same mechanism as
-- GBIF with gbif_key/eltern_key, just named differently.

CREATE TABLE map_tax_dgfm (
    taxnr           int unsigned NOT NULL,
    sipnr           int unsigned NOT NULL COMMENT 'taxnr des gültigen Namens; gleich = akzeptiert',
    rang            varchar(20) NULL,

    binomial        varchar(200) NULL COMMENT 'Name ohne Autor — hiernach wird verglichen',
    artname         varchar(500) NULL COMMENT 'vollständig mit Autor und Jahr',
    gattung         varchar(100) NULL,
    art             varchar(100) NULL,
    autor           varchar(200) NULL,
    jahr            varchar(10) NULL,

    deutscher_name  varchar(255) NULL,
    rote_liste_d    varchar(10) NULL,
    rote_liste_by   varchar(10) NULL,

    familie         varchar(100) NULL,
    ordnung         varchar(100) NULL,
    klasse          varchar(100) NULL,
    gruppe          varchar(100) NULL,

    PRIMARY KEY (taxnr),
    KEY idx_sipnr (sipnr),
    KEY idx_binomial (binomial),
    KEY idx_deutsch (deutscher_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- The marriage of the two lists.
--
-- At most one row per DGfM entry. 'weg' records how the match came about —
-- that is no ornament: a match via the name is stronger than one via a
-- synonym chain, and whoever distrusts an assignment later must be able to
-- see what it rests on.
--
-- 'kandidaten' counts how many GBIF entries matched the name. More than one
-- means identical names for different fungi; such assignments are to be
-- checked instead of adopted silently.

CREATE TABLE map_tax_verknuepfung (
    taxnr           int unsigned NOT NULL COMMENT 'DGfM-Eintrag',
    gbif_key        int unsigned NOT NULL COMMENT 'getroffener GBIF-Eintrag',
    gbif_art_key    int unsigned NULL COMMENT 'dessen akzeptierte Art',
    weg             varchar(24) NOT NULL COMMENT 'name | gbif_synonym | dgfm_synonym',
    kandidaten      smallint unsigned NOT NULL DEFAULT 1,

    PRIMARY KEY (taxnr),
    KEY idx_gbif (gbif_key),
    KEY idx_art (gbif_art_key),
    KEY idx_weg (weg)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
