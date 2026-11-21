-- SEVERAL EDITS OF ONE PICTURE, ONE OF THEM VALID (11.11.2026:
-- „dass man zwei Bearbeitungen auf ein Foto legen kann. Das, was wir jetzt
-- haben, wäre Bearbeitung 1 und dann noch ein Plus dahinter … wo man dann
-- vergleichen kann, oder eine dritte oder mehrere").
--
-- His decisions the same day: a new edit starts as a COPY of the one open;
-- one compares by switching (key V); ONE EDIT IS VALID, marked with a star,
-- and that one counts everywhere — the tiles, the plates, the export, the
-- report, the names and stamps; the edits are only numbered, 1, 2, 3.
--
-- THE VALID EDIT STAYS WHERE IT ALWAYS WAS, in `develop`. Every place that
-- reads a picture's edit — some fifteen, from the Fundbrowser's tiles to
-- the server's find report — goes on reading that one column and never
-- hears of the others. They lie here beside it, as JSON:
--   {"at": 0, "list": [null, {…}, {…}]}
-- `list` holds every edit in its numbered order; the slot `at` is the
-- valid one's place and stays null, since its settings are in `develop`.
-- An empty edit is null as well (photo_develop_versions_clean). Making
-- another one valid swaps the two. NULL — the column's whole value — is
-- „eine einzige Bearbeitung", every picture before.
--
-- MEDIUMTEXT and not TEXT: one edit may run to some forty kilobytes (a
-- hundred retouched spots, photo_develop_clean), and nine of them would
-- not fit into TEXT's 64.
--
-- ON BOTH PICTURE TABLES, as `develop` is — „Bilder sind Bilder".

ALTER TABLE map_cloud_files ADD COLUMN develop_versions MEDIUMTEXT NULL AFTER develop;
ALTER TABLE map_photos ADD COLUMN develop_versions MEDIUMTEXT NULL AFTER develop;
