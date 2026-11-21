-- hoehe_quelle needs more room.
--
-- The column was sized for eight characters, for 'gnss' and 'dgm1'. With the
-- backfill a third value joins: 'ausserhalb' for points lying outside the
-- German elevation model — on a trip to the Alps or Italy quickly dozens.
-- The value is ten characters long and would have been truncated or
-- rejected.
--
-- Noticed while reading, not while running: the first pass happened to hit
-- only German points. Without this change the second would either have
-- aborted with a database error or — worse — stored 'ausserha', and the
-- points would have been queried again on every further run, because that
-- value matches nothing.

ALTER TABLE map_points
    MODIFY hoehe_quelle VARCHAR(16) NULL COMMENT 'gnss | dgm1 | ausserhalb — woher hoehe_m stammt';
