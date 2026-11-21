-- The square and the diamond leave the shape picker (24.09.2026:
-- „dann Quadrat und Raute raus"). Counted in the data set beforehand: no
-- list wore the square, one list wore the diamond — his own „Gebiet
-- vielversprechend", which he has in mind with the check mark. So the
-- diamond goes to the check mark and the square, should one have been
-- created since, to the circle.
--
-- Both names stay known as aliases (LIST_SYMBOL_ALIAS in includes/data.php),
-- for a phone that still holds them.
UPDATE map_lists SET symbol = 'check' WHERE symbol = 'diamond';
UPDATE map_lists SET symbol = 'circle' WHERE symbol = 'square';
