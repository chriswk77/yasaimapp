-- The Messtischblatt for the point.
--
-- WHAT FOR. German mapping reckons in Messtischblätter: one sheet of the
-- TK25, 10 arc minutes wide and 6 high, roughly 11 × 11 km. Whoever reports
-- finds to a mapping body states MTB and quadrant; without them a record is
-- not connectable.
--
-- WHY STORED AND NOT COMPUTED EVERY TIME. Computing costs nothing — it is
-- two divisions. It is stored because one wants to SEARCH and SORT by it:
-- all finds of one sheet, the sheets of one year, an export grouped by
-- sheet. As a derived value in the display that would only work if the
-- database retraced the conversion for every row, and it cannot.
--
-- TWO COLUMNS, NOT ONE. The sheet alone is the usual unit of reference, the
-- quadrant the refinement. Both in one string ("5817/433") could be
-- displayed, but no longer grouped without cutting it apart every time.
--
-- THE QUADRANT HOLDS THREE DIGITS: quarter, sixteenth,
-- sixty-fourth — "433" means southeast, within it southwest, within it
-- southwest. Whoever only needs the quarter reads the first; whoever maps
-- more finely already has the others there. They cost two bytes and would
-- otherwise have to be backfilled later.
--
-- NULL MEANS OUTSIDE. Sheets only exist for Germany. A find in the
-- Dolomites gets no number, and that is as it should be — a computed number
-- for a sheet that was never printed would be worse than none at all.

ALTER TABLE map_points
    ADD mtb CHAR(4) NULL
        COMMENT 'Messtischblatt (TK25), vierstellig' AFTER hoehe_quelle,
    ADD mtb_quadrant CHAR(3) NULL
        COMMENT 'Viertel, Sechzehntel, Vierundsechzigstel — je 1=NW 2=NO 3=SW 4=SO' AFTER mtb,
    -- For "all finds in sheet 5817". The user belongs in it: without them
    -- the index would lie across all accounts and be useless for the one
    -- query it exists for.
    ADD KEY ix_map_points_mtb (user_id, mtb);
