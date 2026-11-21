-- Species suggestions in the custom fields too.
--
-- The title of a species list has been getting suggestions from the name
-- database since migration 0014. The custom fields got none — and that is
-- exactly where the interesting names stand:
--
--   "Substrat / Mykorrhiza-Partner / Wirt"   one plant, one line
--   "Begleitpflanzen"                        several, one below the other
--
-- Both are species names, both are tedious to type, and both exist in the
-- database. That of all places nobody got help there was a gap.
--
-- WHY A CHECKMARK OF ITS OWN AND NOT A NEW TYPE. 'text' and 'mehrzeilig'
-- describe what the field looks like; the suggestions describe what belongs
-- into it. Turning two questions into one select box with four entries
-- ("einzeilig", "einzeilig mit Arten", …) would be less work in the short
-- run and permanently harder to read — and at the third attribute it would
-- not work at all anymore.
--
-- WHY NOT ON THE LIST. It has its own ist_arten, but that applies to the
-- TITLE. A list can hold finds (title = fungus name) and contain a field
-- for the host plant — two different names from two different groups, in
-- the same record. One checkmark for both would not capture it.

ALTER TABLE map_list_felder
    ADD ist_arten TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Vorschläge aus der Namensdatenbank' AFTER typ;
