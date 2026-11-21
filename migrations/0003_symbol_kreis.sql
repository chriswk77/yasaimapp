-- Set the old placeholder symbol to a shape that can actually be drawn.
--
-- Until the marker shapes were introduced, all lists carried the value 'pilz'.
-- It was never drawn; the map only knows kreis, quadrat, dreieck, raute,
-- faehnchen and kreuz and falls back to the circle for anything else.
--
-- The fallback would sort it out anyway, but a value that nothing in the
-- system knows is a stumbling block for later — for example on export.

UPDATE map_lists
   SET symbol = 'kreis'
 WHERE symbol NOT IN ('kreis', 'quadrat', 'dreieck', 'raute', 'faehnchen', 'kreuz');
