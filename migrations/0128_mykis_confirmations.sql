-- The finder's confirmation after a MykIS delivery (25.09.2026:
-- „über die Bestätigungsmail ja, an die einzelnen Finder — aber ein
-- bisschen zeitversetzt, dass erst die großen Mails mit den Funden
-- rausgehen").
--
-- The coordinators get ONE mail per address and half-year, with one
-- sheet per finder attached; afterwards every finder gets one mail saying
-- what went where, with their own sheets. This column records that the
-- confirmation for a delivered find went out — NULL waits. Deliveries
-- booked before this migration stay NULL and are confirmed on the next
-- run; live there were none yet.
ALTER TABLE map_mykis_deliveries
    ADD confirmed_at DATETIME NULL COMMENT 'the finder was told about this delivery; NULL = not yet';
