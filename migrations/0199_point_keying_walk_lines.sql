-- THE CONFIRMED WAYS OF A WALK AS PLAIN LINES (20.11.2026).
--
-- A walk through the identification keys is stored as JSON (0170) — lead
-- ids, which only the tool that wrote them can read. An installation
-- without that tool, or a day on which it is gone, would show a find that
-- was keyed and could not say how. So the tool hands the core one finished
-- line per CONFIRMED way when the walk is saved — the book, the chain of
-- keys, the steps, the species —, and the core keeps them beside the walk
-- and shows them wherever no tool is there to draw the ways itself
-- (includes/point_keyings.php; hook walk_lines in includes/modules.php).
--
-- One line per way, a line break between them. NULL = not made yet (a walk
-- from before this column; the core fills it in the next time the find's
-- ways are read while a tool is there), an empty text = made, and the walk
-- has no confirmed way. The typed way (`way`, 0198) is the keyer's own text
-- and stays apart from it.

ALTER TABLE map_point_keyings
    ADD COLUMN walk_lines TEXT NULL COMMENT 'one finished line per confirmed way of the walk — NULL until made, empty where there is none' AFTER walk;
