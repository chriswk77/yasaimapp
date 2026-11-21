-- The lizard replaces the old sign of the group „Reptilien und Lurche"
-- (24.09.2026: „Eidechse ersetzt das heutige Tier"). The old
-- sign 'amphibian' — a four-legged animal with a tail from above — read as a
-- lizard as much as a newt; the group now has three signs, a frog, a lizard
-- and a snake, and every list that wore the old one wears the lizard.
--
-- The key 'amphibian' stays known as an alias (LIST_SYMBOL_ALIAS in
-- includes/data.php), for a phone that still holds it.
UPDATE map_lists SET symbol = 'lizard' WHERE symbol = 'amphibian';
