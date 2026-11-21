-- stand: the column by which a device recognizes that something changed.
--
-- WHAT FOR. With a few thousand points, the whole holdings can no longer go
-- over the wire on every page load — measured 525 bytes per point, so 10 MB
-- at 20,000. The way out are buckets (years, months for the current year)
-- with one fingerprint each: the device sends what it has, the server says
-- what has changed, and only that comes anew. Past finds do not change
-- anymore — a bucket "2019" is loaded once and never again.
--
-- WHY geaendert_am IS NO GOOD FOR THIS. That is the USER'S timestamp:
-- the queue on the phone decides by it whose version is the younger one
-- when two devices have touched the same point. A derivation the server
-- backfills on the side — municipality, Messtischblatt, species table —
-- is not a change by the user and must not take precedence away from them.
-- That is exactly why gemeinde_eintragen() deliberately does NOT touch
-- geaendert_am.
--
-- Each is right on its own and wrong together. Reenacted on 20,000 rows:
-- 5,000 points got their municipality filled in, and a fingerprint over
-- geaendert_am stayed the same character for character — the change would
-- have reached not a single device. Two questions need two columns; today
-- one column answers two questions and therefore one of them wrongly.
--
--   geaendert_am   "who last wanted something"    → precedence in syncing
--   stand          "did anything happen here"     → fingerprint
--
-- THE DATABASE MAINTAINS IT, NOT THE CODE. That is the whole core. The
-- mistake above was not malice but forgetting: of five writing sites,
-- exactly one thought of the timestamp. A rule that stands in the code must
-- be known to everyone who writes a sixth site next year. A rule that
-- stands in the table cannot be bypassed — ON UPDATE fires on EVERY
-- UPDATE, including the one nobody thinks of anymore.
--
-- And it only fires when something really changes: an UPDATE that writes
-- the same values again leaves stand alone. For a fingerprint that is
-- exactly right — nothing changed, nothing to fetch.
--
-- DATETIME(6) AND NOT TIMESTAMP. TIMESTAMP ends on January 19, 2038; that
-- is twelve years away, and this is a find archive that is supposed to
-- outlast its author. DATETIME reaches to 9999.
--
-- SIX DECIMAL PLACES, that is, microseconds. Two writes within the same
-- second must be distinguishable, or the fingerprint misses a change — and
-- that of all places during chunked backfills, where dozens of points pass
-- through per second.

ALTER TABLE map_points
    ADD stand DATETIME(6) NOT NULL
        DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
        COMMENT 'Jede Änderung, auch abgeleitete — für den Fingerabdruck'
        AFTER geaendert_am;

-- The existing data gets an honest starting value instead of "everything
-- changed just now". An explicitly set value overrides ON UPDATE, which is
-- wanted here: otherwise all buckets would look fresh after the migration,
-- and the first device would load the whole holdings once more — exactly
-- what the column is supposed to prevent.
UPDATE map_points SET stand = COALESCE(geaendert_am, erstellt_am);
