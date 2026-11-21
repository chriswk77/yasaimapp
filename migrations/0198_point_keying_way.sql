-- THE KEYING WAY AS TEXT, TYPED BY HAND (20.11.2026).
--
-- Until now a way at a find came only out of the identification module: a
-- walk through its keys, stored as JSON (0170). Whoever keys in the book
-- itself had no place to note how he reached the name. Decided that day:
-- „ein Text-Area-Feld, falls man in zwei Büchern geschlüsselt hat, dass man
-- eine neue Zeile machen kann" — one field under „Art", a line per book.
--
-- `way` is that text, one per find and person like the walk beside it:
-- nobody's way is overwritten by another's (0170 says why). A row may now
-- hold a typed way and no walk at all, so `walk` gives up its NOT NULL; a
-- row left with neither is deleted by the code (includes/point_keyings.php).

ALTER TABLE map_point_keyings
    MODIFY walk MEDIUMTEXT NULL COMMENT 'the identification walk as JSON (keys.js saveWalk) — NULL where the way was only typed',
    ADD COLUMN way TEXT NULL COMMENT 'the way as its keyer typed it — a line per book' AFTER walk;
