-- Extend the link table with the Catalogue of Life.
--
-- So far it tied the German list only to GBIF. But GBIF's backbone has stood
-- still since August 2023, and of the shared names 3,977 have changed their
-- status since — for those, GBIF delivers not a gap but a wrong answer.
-- That is why COL joins in, and in case of contradiction COL wins:
-- for a find of today, today's name counts.
--
-- The old columns are now called weg_gbif and kandidaten_gbif so that they
-- differ from their COL counterparts. And gbif_key may be empty — a name
-- can appear in COL and be missing from GBIF.
--
-- 'gueltiger_name' is the result of the whole exercise: the currently valid
-- name for a DGfM entry. 'quelle_gueltig' records where it comes from —
-- without that it would not be traceable which list an answer is owed to.

ALTER TABLE map_tax_verknuepfung
    MODIFY gbif_key int unsigned NULL COMMENT 'getroffener GBIF-Eintrag',
    CHANGE weg        weg_gbif        varchar(24) NULL COMMENT 'name | gbif_synonym | dgfm_synonym',
    CHANGE kandidaten kandidaten_gbif smallint unsigned NULL,

    ADD col_id         varchar(24) NULL COMMENT 'getroffener COL-Eintrag'          AFTER gbif_art_key,
    ADD col_art_id     varchar(24) NULL COMMENT 'dessen akzeptierter Name'         AFTER col_id,
    ADD weg_col        varchar(24) NULL COMMENT 'name | col_synonym | dgfm_synonym' AFTER col_art_id,
    ADD kandidaten_col smallint unsigned NULL                                       AFTER weg_col,

    ADD gueltiger_name varchar(255) NULL COMMENT 'heute gültiger Name',
    ADD quelle_gueltig varchar(8) NULL COMMENT 'col | gbif | dgfm — wer ihn liefert',

    ADD KEY idx_col (col_id),
    ADD KEY idx_gueltig (gueltiger_name);
