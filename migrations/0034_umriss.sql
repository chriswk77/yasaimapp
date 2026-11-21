-- Areas: a point may bring its outline along.
--
-- WHY NO TABLE OF ITS OWN. An area is not a different thing from a point,
-- it is a point with extent. It lies in the same lists, carries the same
-- custom fields, the same voucher photos, the same Messtischblatt, goes
-- through the same queue and ends up in the same trash. A second table
-- would mean building all of that a second time — and at the next addition
-- a third time.
--
-- LAT AND LON REMAIN THE MAIN THING. For an area they point to a spot
-- *inside it* (see mittelpunkt() in assets/js/flaeche.js) and are still
-- what sheet and terrain elevation are derived from. Everything points can
-- already do — search, viewport, sync, export — thus applies unchanged to
-- areas too, without touching a single query.
--
-- JSON AND NOT TEXT. Measured, because coordinates otherwise like to pick
-- up rounding drift: MySQL 8 returns [[50.123456, 8.654321], …] digit for
-- digit as PHP wrote it in. The worry that stands behind the DECIMAL for
-- lat/lon thus does not apply here.
--
-- WITHOUT AN INDEX. Searches go for points, not for outlines; the map
-- draws anyway what it has loaded without a detour.

ALTER TABLE map_points
    ADD umriss JSON NULL
        COMMENT 'Eckpunkte der Flaeche als [[lat, lon], ...], sonst NULL' AFTER lon;
