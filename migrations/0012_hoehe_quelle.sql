-- Where the elevation comes from.
--
-- hoehe_m holds the best available height above NHN. So far it could only
-- come from the satellite receiver; now there is a second route that is an
-- order of magnitude better:
--
--   gnss   converted measurement of the receiver   ±10 m
--   dgm1   terrain model of the state survey       ±0.3 m
--
-- Without this column one could not tell from a number which of the two
-- cases applies — and thus also not whether it is worth the meter it looks
-- accurate to. For a documentation, that is the difference between a record
-- and a number.
--
-- The raw measurement in hoehe_ellipsoid_m stays untouched in both cases.

ALTER TABLE map_points
    ADD hoehe_quelle VARCHAR(8) NULL COMMENT 'gnss | dgm1 — woher hoehe_m stammt' AFTER hoehe_genauigkeit_m;

UPDATE map_points
   SET hoehe_quelle = 'gnss'
 WHERE hoehe_m IS NOT NULL AND hoehe_ellipsoid_m IS NOT NULL;
