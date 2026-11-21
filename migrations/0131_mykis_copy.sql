-- The finder's copy of a DGfM delivery becomes their choice
-- (25.09.2026: „für die Bestätigungs-E-Mail, wenn man eine Kopie möchte,
-- hier auch ein Haken … mit den entsprechenden Funden, die an den
-- Landeskoordinator gingen").
--
-- Until now every finder got one after every delivery (0128). From now
-- on only whoever ticks it — the tick starts off for everyone, including
-- whoever agreed before this column existed. What it is good for: seeing
-- what went, and sending on by hand what did not — a name the TaxRef
-- lacked, which Frank Dämmrich then adds to it.
ALTER TABLE map_users
    ADD mykis_copy TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = a copy of every DGfM delivery to the finder';

-- A delivery of a finder without the tick is settled when it is booked,
-- so nothing waits for a mail that will never go.
ALTER TABLE map_mykis_deliveries
    MODIFY confirmed_at DATETIME NULL COMMENT 'settled for the finder: copy sent, or none wanted; NULL = copy still to send';
