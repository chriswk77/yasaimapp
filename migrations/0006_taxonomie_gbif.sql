-- GBIF taxonomy of the fungi as the basis for synonymization.
--
-- Purpose: recognizing that two names mean the same fungus. Whoever files a
-- find spot under "Boletus aereus" and later searches under "Boletus
-- mamorensis" is supposed to find it.
--
-- Source: GBIF Backbone Taxonomy, release of 28.08.2023 (the current state;
-- GBIF itself points to it), filtered to kingdom Fungi. Publicly available
-- without registration — so the table can be rebuilt at any time, which
-- after the loss of the pilzlinks database was the decisive argument.
--
-- Structure of the synonymy: every entry has a gbif_key. If it is a synonym,
-- eltern_key points to the accepted name. The same mechanism as in the DGfM
-- list, there via taxnr/sipnr.
--
-- Deliberately a flat table instead of normalized ranks: the hierarchy is
-- already contained in the *_key columns, and any decomposition would
-- complicate the queries without gaining anything.

CREATE TABLE map_tax_gbif (
    gbif_key        int unsigned NOT NULL COMMENT 'GBIF usageKey',
    eltern_key      int unsigned NULL COMMENT 'bei Synonymen: der akzeptierte Name',
    basionym_key    int unsigned NULL,
    ist_synonym     tinyint unsigned NOT NULL DEFAULT 0,
    status          varchar(20) NULL COMMENT 'ACCEPTED, SYNONYM, DOUBTFUL …',
    rang            varchar(20) NULL COMMENT 'SPECIES, GENUS, VARIETY …',

    phylum_key      int unsigned NULL,
    klasse_key      int unsigned NULL,
    ordnung_key     int unsigned NULL,
    familie_key     int unsigned NULL,
    gattung_key     int unsigned NULL,
    art_key         int unsigned NULL COMMENT 'akzeptierte Art, gruppiert Synonyme',

    name            varchar(400) NOT NULL COMMENT 'wissenschaftlicher Name mit Autor',
    kanonisch       varchar(255) NULL COMMENT 'Name ohne Autor — hiernach wird gesucht',
    gattung         varchar(120) NULL,
    epitheton       varchar(120) NULL,
    infra_epitheton varchar(120) NULL,
    autor           varchar(255) NULL,
    basionym_autor  varchar(255) NULL,

    PRIMARY KEY (gbif_key),
    KEY idx_art (art_key),
    KEY idx_eltern (eltern_key),
    -- Searching by name runs on the canonical name, not the one with the
    -- author: nobody types "(Bull.) Fr." along with it.
    KEY idx_kanonisch (kanonisch),
    KEY idx_gattung (gattung, epitheton),
    KEY idx_status (status, rang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
