-- The triangle leaves the shape picker (24.09.2026: „nur Finn
-- verwendet das Dreieck, für Waldbrände. Dann lösche das Dreieck und gib
-- Finn für die Waldbrände die Flamme"). Counted beforehand: Finn's two
-- lists „Waldbrände 2026", the maintainer's empty „Waldbrände 2026 (ehemals
-- gemeinsam)" and the maintainer's „Campingplätze" — for which he asked for a
-- sign of its own, the new 'campsite'.
--
-- By name, because the name is what decides here; anything else still
-- wearing the triangle goes to the circle. The name stays known as an
-- alias (LIST_SYMBOL_ALIAS in includes/data.php).
UPDATE map_lists SET symbol = 'fire' WHERE symbol = 'triangle' AND name LIKE 'Waldbrände%';
UPDATE map_lists SET symbol = 'campsite' WHERE symbol = 'triangle' AND name = 'Campingplätze';
UPDATE map_lists SET symbol = 'circle' WHERE symbol = 'triangle';
