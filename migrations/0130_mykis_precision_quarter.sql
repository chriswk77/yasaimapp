-- The quarter quadrant is the floor AND the default of the DGfM precision
-- (25.09.2026, an hour after 0129: „nur Messtischblatt geht
-- nicht — der Viertelquadrant ist das Ungenaueste, was wir haben", and
-- „genau dahin auch den Standard — wir wollen niemandem etwas aufzwingen,
-- was er nicht will").
--
-- 0129 offered the sheet alone and read NULL as exact coordinates; both
-- went. NULL now means the quarter (MYKIS_PRECISION_DEFAULT in
-- includes/mykis.php), so nobody hands over more than they chose by their
-- own hand. Whoever picked the sheet in that hour chose the coarsest there
-- was, and the quarter is the coarsest there is. The code reads a stray
-- 'sheet' as the quarter anyway — this makes the column say what it holds.
UPDATE map_users SET mykis_precision = 'q4' WHERE mykis_precision = 'sheet';

ALTER TABLE map_users
    MODIFY mykis_precision VARCHAR(10) NULL COMMENT 'exact, q64, q16 or q4; NULL = q4, the coarsest and the default';
