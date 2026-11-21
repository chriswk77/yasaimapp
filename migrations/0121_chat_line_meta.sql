-- What a change line is about — so the next one can fold into it
-- (24.09.2026, after creating one burn site in „Waldbrände
-- 2026": five lines within six minutes — set, titled, noted, moved,
-- outlined. „Bei einem neuen Punkt erstellen das doch etwas übertrieben
-- … wir könnten das etwas komprimieren.").
--
--   meta   JSON: the point the line speaks of, whether it began as the
--          point's creation, and the changed fields with their first
--          „before" and latest „after". The joint-list code folds a new
--          change of the same member on the same point into the latest
--          line of the share when nothing else was said in between
--          (includes/joint_lists.php); the replaced line's id rides along
--          as „replaces", so an open chat swaps the line instead of
--          showing both. NULL for spoken messages and for every line
--          written before this migration — those simply never fold.
ALTER TABLE map_container_messages
    ADD COLUMN meta TEXT NULL;
