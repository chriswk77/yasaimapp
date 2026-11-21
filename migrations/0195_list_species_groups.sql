-- WHAT A LIST HOLDS (12.11.2026). Until now one choice per
-- device — Einstellungen → Arten, fungi for whoever never chose — decided
-- which names were suggested in EVERY species list and which name packages
-- lay on the phone. Now each list says what is in it: the fungi list
-- suggests fungi, the lichen list lichens, a fish list fish. The packages
-- a device keeps follow from the lists it can write into (own and shared),
-- and come at once, not at the first typed name — „wenn ich im Wald stehe
-- und keinen Empfang habe, würde mich das ärgern".
--
-- A JSON array of group keys (species_groups_all() in includes/species.php:
-- fungi, lichens, plants and the animal groups of ANIMAL_GROUPS). NULL is
-- „not chosen yet" and reads as fungi, the old default of the device
-- setting, so no list falls silent by this migration.
--
-- THE START LISTS ARE KNOWN BY NAME. Every account was given „Flechten",
-- „Pflanzen" and „Pilze" (includes/auth.php), and most have kept the
-- names; a list called like a group gets that group. The reasoning:
-- the names are the user's to change, but where they are unchanged, they
-- say exactly what the list holds. A list of another name stays NULL and
-- is ticked in its window „Liste bearbeiten".

ALTER TABLE map_lists ADD COLUMN species_groups VARCHAR(255) NULL AFTER is_species;

UPDATE map_lists SET species_groups = '["fungi"]'      WHERE name = 'Pilze';
UPDATE map_lists SET species_groups = '["lichens"]'    WHERE name = 'Flechten';
UPDATE map_lists SET species_groups = '["plants"]'     WHERE name = 'Pflanzen';
UPDATE map_lists SET species_groups = '["insects"]'    WHERE name = 'Insekten';
UPDATE map_lists SET species_groups = '["arachnids"]'  WHERE name = 'Spinnentiere';
UPDATE map_lists SET species_groups = '["molluscs"]'   WHERE name = 'Weichtiere';
UPDATE map_lists SET species_groups = '["birds"]'      WHERE name = 'Vögel';
UPDATE map_lists SET species_groups = '["mammals"]'    WHERE name = 'Säugetiere';
UPDATE map_lists SET species_groups = '["reptiles"]'   WHERE name = 'Reptilien';
UPDATE map_lists SET species_groups = '["amphibians"]' WHERE name = 'Amphibien';
UPDATE map_lists SET species_groups = '["fish"]'       WHERE name = 'Fische';
