-- THE FINDS THAT WERE WRONGLY NOTED AS LYING IN NO PROTECTED AREA ARE ASKED
-- AGAIN (30.09.2026).
--
-- The protected-area service of the BfN was rebuilt between the 24th and
-- the 30th of September 2026. Since then it reads a query box in its own
-- grid (UTM 32N) and finds nothing for a box in degrees — HTTP 200, an
-- empty collection, no error. Every find saved or moved from then on got
-- the empty text, which means "asked, lies in none" (protected_area_record
-- in includes/protected_areas.php), while it may well lie in a nature
-- reserve. The code now asks in UTM (same push as this migration).
--
-- NULL means "never asked", and the catch-up asks every NULL find again —
-- Nachtragen → Schutzgebiete, and its cron twin. Only the empty text from
-- the 24th on goes back: named areas were right until the rebuild (the
-- local copy of 24.09. still carries them, the FFH names included), and an
-- empty text from before is an honest "none". A find asked once more
-- costs three requests to the BfN and nothing else.
--
-- MUST GO LIVE AFTER THE CODE: applied before it, the catch-up would ask
-- the old way and write the empty text right back.

UPDATE map_points
   SET protected_area = NULL
 WHERE protected_area = ''
   AND (created_at >= '2026-09-24' OR updated_at >= '2026-09-24');
