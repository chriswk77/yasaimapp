-- The tour: a named outing, written on every point created during it.
--
-- A NAME ON THE POINT, NOT A TABLE OF ITS OWN, and that is a deliberate
-- trade. A tour table with ids would need provisional ids for tours begun
-- in a dead zone — the whole queue machinery a second time, for a record
-- that is nothing but a label. As a text on the point, the tour travels
-- through create, queue, bucket sync and conflict handling like the title
-- does, with no new moving parts. Renaming a running tour rewrites the few
-- points of that outing through the ordinary PATCH path.
--
-- NULL means: created before tours existed, or outside any tour. The
-- filter shows such points under their day, as before — nothing old
-- changes its meaning.

ALTER TABLE map_points
    ADD COLUMN tour VARCHAR(120) NULL AFTER find_number;
