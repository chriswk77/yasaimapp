-- A reference beside the name, not in its place.
--
-- UNTIL NOW A FIELD HELD ONLY TEXT. Whoever entered "Quercus robur" had the
-- character string afterwards — and if the name later becomes a synonym,
-- nobody notices. A reference to the entry in the name list can do that:
-- it keeps pointing to the same taxon even when its valid name changes.
--
-- BESIDE THE NAME, NOT IN ITS PLACE. A find record holds what somebody
-- identified back then. If the name changes silently after the fact, it can
-- no longer be said later in which species concept the identification stood
-- — and that is exactly the piece of information a re-identification
-- depends on. A herbarium specimen is not relabeled either; one writes
-- today's name next to it.
--
-- Hence a column of its own instead of a change to zusatz: the text stays
-- untouched, old entries keep their form, and a field without a reference
-- is not a special case but the normal case — "Cortinarius spec.", a still
-- undescribed species or "Fichte, vermutlich" appear in no list.
--
-- THE IDENTIFIER IS "quelle:wert", for example "pilze:12345" or
-- "flechten:7b765d93-ad90-4d86-b4aa-0e33e2213d7a". One character string
-- instead of two columns, because it travels through JSON, interface and
-- package format and can be treated as one piece everywhere.

ALTER TABLE map_points
    ADD zusatz_arten JSON NULL
        COMMENT 'je Feldname der Verweis auf die Namensliste, "quelle:kennung"' AFTER zusatz;

-- The stable identifier of the two BfN lists.
--
-- WHY NOT taxnr: that one is assigned by the import script itself, running
-- in name order. A corrected edition shifts everything, and a reference
-- would afterwards point to a different species. That is not an identifier,
-- that is a position.
--
-- The source, by contrast, carries a UUID per taxon — for the lichens under
-- "UUID des Taxons", for the plants under "Publikations-Id des Taxons".
-- The same thing, two column names; both are filled for all rows and unique.
--
-- It stays NULL for the synonym rows: those arise from the column
-- "Synonyme" and have no entry of their own in the source. References go to
-- the valid name anyway, always.
ALTER TABLE map_tax_flechten
    ADD uuid CHAR(36) NULL COMMENT 'stabile Kennung aus der Quelle' AFTER taxnr,
    ADD KEY (uuid);

ALTER TABLE map_tax_pflanzen
    ADD uuid CHAR(36) NULL COMMENT 'stabile Kennung aus der Quelle' AFTER taxnr,
    ADD KEY (uuid);
