-- Municipality, state and country for the point.
--
-- WHAT FOR. The Messtischblatt is for the mapping body; to a human "5817"
-- says nothing. "DE/BAY/Mittenwald" says everything — and is at the same
-- time the scheme by which the voucher photos lie on the hard disk. With
-- that, a row in this database and a folder there become the same thing.
--
-- THREE COLUMNS, NOT ONE. As a finished path "DE/BAY/Mittenwald" the value
-- could be displayed and nothing else. Separated, one can group by state,
-- count by municipality, pull out all Swiss finds — and reassemble the
-- path from them at any time. The other way round there would only be
-- cutting at a string on every query.
--
-- WHY STORED AT ALL. Unlike the Messtischblatt this is not a calculation
-- but information from outside: it costs a request over the network and is
-- unavailable when nobody answers. And it is not stable — municipalities
-- merge, Switzerland especially eagerly. The value in the point is the
-- name valid at find time, and exactly that stands on the hard disk.
--
-- landesteil IS VARCHAR(6), NOT CHAR(3). For Germany, Austria and
-- Switzerland three letters from a table of our own stand there. For
-- everything else the ISO remainder stays, and that is sometimes two
-- characters long, sometimes three ("IT-32", "FR-ARA"). A fixed width
-- would have truncated that silently.
--
-- gemeinde_quelle KNOWS 'osm' AND 'ohne'. The second means: there really
-- is no municipality there — open sea, no-man's-land. Without this note
-- the backfill would ask again on every run about the same point in the
-- middle of the Atlantic. NULL, by contrast, means "not asked yet", and
-- that is a difference.

ALTER TABLE map_points
    ADD gemeinde VARCHAR(120) NULL
        COMMENT 'Name der Gemeinde, wie zur Fundzeit gültig' AFTER mtb_quadrant,
    ADD landesteil VARCHAR(6) NULL
        COMMENT 'Bundesland/Kanton, drei Buchstaben in DACH, sonst ISO-Rest' AFTER gemeinde,
    ADD staat CHAR(2) NULL
        COMMENT 'ISO-3166-1, grossgeschrieben' AFTER landesteil,
    ADD gemeinde_quelle VARCHAR(16) NULL
        COMMENT 'osm | ohne — NULL heisst noch nicht gefragt' AFTER staat,
    -- For "all finds in this municipality" and for grouping by state. The
    -- user belongs in it, for the same reason as with the sheet index:
    -- without them the index would lie across all accounts and be useless
    -- for the one query it exists for.
    ADD KEY ix_map_points_gemeinde (user_id, staat, landesteil, gemeinde);
