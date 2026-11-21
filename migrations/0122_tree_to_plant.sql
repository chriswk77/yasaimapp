-- The conifer gives way to the seedling (24.09.2026: „ich will,
-- dass die Tanne gegen den Keimling getauscht wird"). Every list that wore
-- the shape 'tree' wears 'plant' from now on — the starter lists
-- „Pflanzen" of every account among them.
--
-- The name 'tree' stays known as an alias (LIST_SYMBOL_ALIAS in
-- includes/data.php): a phone that was offline when this ran still holds
-- the old name and draws the seedling all the same, and a save it queued
-- lands as 'plant' instead of a circle.
UPDATE map_lists SET symbol = 'plant' WHERE symbol = 'tree';
