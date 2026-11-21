-- The find time comes from the device, not from the server.
--
-- WHAT WAS WRONG. erstellt_am is set by the server with NOW(). A point set
-- without reception thus only gets its timestamp when it is submitted
-- later — if in doubt, hours later on the home WLAN. A whole tour thereby
-- landed in the same minute, and of all places in the dead zone: exactly
-- where mapping happens. Everything temporal ("last tour", "period from
-- to") would rest on this wrong statement.
--
-- gefunden_am would exist for this, but was never written on creation — of
-- 145 organically grown points, two carried it, both from the Takeout
-- import.
--
-- WHY THIS COLUMN AND NOT A FOURTH. Three time statements per point are
-- enough already, and the meaning fits: "when was this found" is exactly
-- what the device's clock knows while one stands in front of it. The
-- server keeps, with erstellt_am, when it learned of it; keeping the two
-- apart is the whole purpose.
--
-- WHY DATETIME AND NOT DATE. A tour is not a calendar unit. It ends when
-- nothing was recorded for a few hours — otherwise midnight cuts a night
-- excursion in two, and two rounds on the same day merge into one. For
-- that, the time of day is needed.
--
-- WHAT HAPPENS TO THE EXISTING DATA. The two points from the import carry
-- midnight afterwards. That is honest: their time of day is not known, and
-- an invented one would be worse than a visibly round one.
--
-- The code is on the server before the migration. In that window it writes
-- a point in time into the still narrow DATE column; measured under
-- STRICT_TRANS_TABLES it is truncated to the day and not rejected. Points
-- can thus be created throughout, they only lose the time of day.

ALTER TABLE map_points
    MODIFY gefunden_am DATETIME NULL
        COMMENT 'Zeitpunkt des Fundes nach der Uhr des Geraets, nicht des Servers';
