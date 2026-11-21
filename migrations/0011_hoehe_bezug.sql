-- Make elevation traceable: raw measurement, conversion, uncertainty.
--
-- So far there was a column hoehe_m with no statement of what it refers to.
-- As long as nobody filled it, that went unnoticed; since the map creates
-- points at one's own location, it is a matter of honesty.
--
-- A satellite receiver delivers the height above the WGS84 ellipsoid, not
-- above sea level. In Germany, around 46 meters lie in between. Conversion
-- happens on creation via the EGM96 grid (see daten/geoid/LIESMICH.md);
-- hoehe_m afterwards contains the height above NHN.
--
-- Why the raw measurement stays anyway: geoid models get better. Whoever
-- wants to recompute in ten years with GCG2016 or its successor needs the
-- original number — from the converted value it can only be computed back
-- if one knows which model was used for the conversion.
--
-- And the uncertainty belongs with it, because it is large: a receiver is
-- two to three times less accurate vertically than horizontally. A height
-- without its uncertainty is, in a documentation, not a record but a claim.

ALTER TABLE map_points
    MODIFY hoehe_m SMALLINT NULL COMMENT 'Höhe über NHN in Metern, umgerechnet',
    ADD hoehe_ellipsoid_m   SMALLINT NULL COMMENT 'Rohmessung über WGS84-Ellipsoid' AFTER hoehe_m,
    ADD hoehe_genauigkeit_m SMALLINT UNSIGNED NULL COMMENT 'Unsicherheit der Höhenmessung in Metern' AFTER hoehe_ellipsoid_m;
