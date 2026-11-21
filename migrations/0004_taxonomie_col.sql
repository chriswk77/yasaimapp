-- Catalogue of Life as a third name source.
--
-- Why a third one: the GBIF backbone dates from 28.08.2023 and is no longer
-- being rebuilt. For species recombined since then, its synonymy is simply
-- wrong — not just incomplete. COL is released monthly and closes exactly
-- this gap.
--
-- Why GBIF stays anyway: COL is smaller (328,288 fungus names against
-- 397,511) and more curated. Where GBIF merges many sources, COL selects.
-- For the question "do two names mean the same fungus", both matter — scope
-- and currency —, which is why the two stand side by side instead of one
-- replacing the other.
--
-- The structure is the same as for the other two: one entry, one reference
-- to the valid name. At COL the key is called col_id and the reference
-- akzeptiert_id; the identifiers are short strings like "9JMY", not
-- numbers.

CREATE TABLE map_tax_col (
    col_id          varchar(24) NOT NULL COMMENT 'ColDP-Kennung, z. B. 9JMY',
    akzeptiert_id   varchar(24) NULL COMMENT 'bei Synonymen: der gültige Name',
    eltern_id       varchar(24) NULL COMMENT 'übergeordnetes Taxon',
    status          varchar(30) NULL COMMENT 'accepted, synonym, ambiguous synonym …',
    rang            varchar(24) NULL,

    name            varchar(400) NOT NULL COMMENT 'wissenschaftlicher Name mit Autor',
    kanonisch       varchar(255) NULL COMMENT 'Name ohne Autor — hiernach wird verglichen',
    autor           varchar(255) NULL,

    familie         varchar(120) NULL,
    ordnung         varchar(120) NULL,
    klasse          varchar(120) NULL,

    PRIMARY KEY (col_id),
    KEY idx_akzeptiert (akzeptiert_id),
    KEY idx_kanonisch (kanonisch),
    KEY idx_status (status, rang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
