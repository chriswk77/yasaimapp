-- AUFSAMMLUNGEN — WHAT IS COLLECTED IN THE FIELD AND CULTURED AT HOME
-- (15.10.2026: dung in a moist chamber, bark for myxomycetes).
--
-- An Aufsammlung is a POINT of its own kind (is_collection): place,
-- collecting day (found_at), substrate (the field „Substrat / Wirt"),
-- finder, pictures of the dung — everything a point already has. What
-- grows from it are ORDINARY FINDS that point back to it (collection_id);
-- each keeps its own species, find number, pictures, microscopy and
-- voucher, and the same species may come several times, one find per
-- fruiting (his call: the pictures must belong to the microscopy).
--
-- THE FIND DATE OF A SPECIES IS THE COLLECTING DAY of its Aufsammlung —
-- place and finder likewise; the code keeps them in step. Researched the
-- same day: the iNaturalist forum and herbarium practice date moist-chamber
-- finds by the field collection, and Darwin Core separates eventDate from
-- dateIdentified — which is why the species gets identified_at.

ALTER TABLE map_points
    ADD COLUMN is_collection TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = an Aufsammlung (substrate in culture), not a find itself',
    ADD COLUMN collection_id INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'the Aufsammlung this find grew from (ex cultura)',
    ADD COLUMN collection_number VARCHAR(20) NULL DEFAULT NULL
        COMMENT 'the Aufsammlung''s own number for the box, A-YYYY-NN',
    ADD COLUMN cultured_at DATE NULL DEFAULT NULL
        COMMENT 'Aufsammlung: the day it went into culture',
    ADD COLUMN culture_ended_at DATE NULL DEFAULT NULL
        COMMENT 'Aufsammlung: the day the culture was ended — NULL while it runs',
    ADD COLUMN identified_at DATE NULL DEFAULT NULL
        COMMENT 'the day the species was determined (dateIdentified)',
    ADD KEY idx_points_collection (collection_id);
