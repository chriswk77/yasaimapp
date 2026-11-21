-- The harvestman seen from the side takes the key 'harvestman'
-- (24.09.2026: „Seitenansicht gewinnt ganz klar"). For an hour it stood as
-- its own shape 'harvestman_side' beside the one from above, so he could
-- compare the two live; every list that picked it meanwhile wears
-- 'harvestman' from now on — which now draws exactly that side view.
--
-- The name stays known as an alias (LIST_SYMBOL_ALIAS in
-- includes/data.php), for a phone that still holds it.
UPDATE map_lists SET symbol = 'harvestman' WHERE symbol = 'harvestman_side';
