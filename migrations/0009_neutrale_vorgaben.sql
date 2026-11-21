-- Default values without a theme.
--
-- The base schema set 'pilz' as the default marker shape, from the time when
-- the application was meant for a single purpose. But it is a general map: a
-- list can hold viewpoints, springs or parking spots. A circle is the shape
-- that fits everything.
--
-- The existing rows were already converted by 20260807_symbol_kreis.sql;
-- what remained open was only the column default that every newly created
-- list inherits.
--
-- 'pilz' remains available as a selectable shape — it stands on equal
-- footing with circle, square, triangle, diamond, flag and cross.

ALTER TABLE map_lists
    MODIFY symbol VARCHAR(40) NOT NULL DEFAULT 'kreis';
