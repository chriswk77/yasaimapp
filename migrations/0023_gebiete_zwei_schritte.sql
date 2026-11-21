-- Areas: drawing the outline and loading tiles are two separate work steps.
--
-- Until now a single window did both. Whoever just wanted to outline a
-- patch of forest was served layer selection, zoom slider and size figures
-- — questions that do not arise at the desk at all. They only arise where
-- loading happens.
--
-- From that follows who owns what, and the split is the same as already
-- with the tiles:
--
--   THE OUTLINE belongs to the user. Name and polygon, nothing else. It is
--   the same on every one of their devices and never expires — a patch of
--   forest does not stop existing.
--
--   ZOOM LEVEL AND EXPIRY belong to the device. On the phone one maybe
--   takes only up to level 17 and for two weeks, on the tablet everything
--   and permanently. As shared values they were simply wrong: one device
--   would have overwritten the other's decision. They therefore move into
--   the browser's storage, right next to the tiles they apply to.
--
-- THE MEASUREMENTS, in turn, belong to everyone again. What a tile of this
-- layer weighs at this spot depends on the terrain, not the device — an
-- aerial image over dense forest weighs the same for everyone. Until now
-- every device determined them anew each time the window opened: a dozen
-- requests to third-party official services for a number that hardly
-- changes within a year. Measure once, remember for all.

ALTER TABLE map_gebiete
  ADD COLUMN messwerte JSON NULL
      COMMENT 'gemessene Bytes je Anfrage, Schluessel "Ebene|Zoomstufe"'
      AFTER ecken,
  ADD COLUMN gemessen_am DATE NULL
      COMMENT 'wann gemessen wurde; danach altert die Auskunft'
      AFTER messwerte;

-- These two move to the device. What gets lost here is a zoom level and a
-- date — both to be set anew at the moment loading actually happens, and
-- that is where the question belongs anyway. Name and outline, that is, the
-- work that really went into it, remain untouched.
ALTER TABLE map_gebiete
  DROP COLUMN zoom_bis,
  DROP COLUMN gueltig_bis;
