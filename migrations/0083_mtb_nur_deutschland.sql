-- The Messtischblatt only holds in Germany (25.08.2026: „Ich
-- habe gesehen, dass auch für österreichische Funde das MTB angegeben
-- wird. Das gilt da allerdings gar nicht mehr").
--
-- WHY IT HAPPENED. The sheet number is computed when a point is created,
-- and at that moment nobody knows its country — only the coordinates are
-- there. mtb.php guards with a bounding RECTANGLE, 47.20 to 55.10 north
-- and 5.80 to 15.10 east, and the whole west of Austria lies inside it:
-- Vorarlberg, Tyrol, Salzburg. Those finds got a number that has never
-- been printed as a sheet.
--
-- The arbitrariness showed itself in the stock: of five Austrian points
-- four carried a sheet and one did not — that one simply lay outside the
-- box. A rule nobody can see through is worse than no rule.
--
-- THE CODE NOW DROPS IT AT THE RIGHT MOMENT: municipality.php writes the
-- country after the reverse geocoding, and clears the sheet right there
-- when the country is not Germany. That reaches every point from now on.
-- What it cannot reach is the stock, and that is what this file is for.
--
-- ONLY WHERE THE COUNTRY REALLY CAME BACK. An empty country means
-- unknown, not foreign — the geocoding may not have run yet, and dropping
-- a right number for that would be the opposite mistake.
UPDATE map_points
   SET mtb = NULL, mtb_quadrant = NULL
 WHERE country IS NOT NULL
   AND country <> ''
   AND UPPER(country) <> 'DE'
   AND mtb IS NOT NULL;
