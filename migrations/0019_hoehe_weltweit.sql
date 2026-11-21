-- Points abroad get an elevation after all.
--
-- During the backfill, points outside Germany were noted as 'ausserhalb' so
-- that not every run asks the German service again about the same spots.
-- That was right as long as there was only the one.
--
-- Now there is a second: the Copernicus elevation model GLO-90, worldwide.
-- With it, these points are no longer hopeless, just less accurate
-- — 90 meters mesh width instead of one. The note is therefore withdrawn,
-- and the backfill picks them up again on the next run.
--
-- 'ausserhalb' stays as a value: there are spots where even the worldwide
-- model yields nothing, and those should still not be queried again on
-- every run.

UPDATE map_points
   SET hoehe_quelle = NULL
 WHERE hoehe_quelle = 'ausserhalb';
