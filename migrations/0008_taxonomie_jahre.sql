-- Add the author year to GBIF, author check for the link table.
--
-- Must run after 0007_verknuepfung_col.sql: autor_passt goes after
-- kandidaten_col, and that column only comes into being there. That is what
-- the number is for.
--
-- A species name comes with author and year. Without them, names are
-- ambiguous: Agaricus campestris L. and Agaricus campestris Schaeff. are
-- different fungi, and in the link table 4,754 assignments stayed open for
-- exactly that reason.
--
-- For the year, though, the yield is limited, and that lies in the
-- nomenclature itself: zoological names cite the year, botanical and
-- mycological ones do not. Measured, 68.7 % of animals at GBIF carry a year,
-- but only 11.5 % of fungi; COL comes to under one percent for fungi. Only
-- the DGfM list has it almost throughout (98.3 %) and is thus the year source.
--
-- For resolving the ambiguities, it is therefore mainly the author that
-- serves. 'autor_passt' records whether it confirmed the assignment — an
-- assignment with a confirmed author is reliable, one without stays a
-- suggestion.

ALTER TABLE map_tax_gbif
    ADD jahr          varchar(10) NULL COMMENT 'Jahr der Kombination, bei Pilzen selten belegt' AFTER autor,
    ADD basionym_jahr varchar(10) NULL AFTER basionym_autor;

ALTER TABLE map_tax_verknuepfung
    ADD autor_passt tinyint unsigned NULL
        COMMENT '1 = Autor bestätigt die Zuordnung, 0 = widerspricht, NULL = nicht prüfbar'
        AFTER kandidaten_col;
