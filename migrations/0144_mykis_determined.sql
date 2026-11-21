-- The finds of other finders, split in two (27.09.2026). Until
-- now one tick (mykis_foreign, 0133) sent every find of one's stock that
-- names somebody else as finder. It had been asked for the finds one only
-- determined — and it also sent the lists one takes over: a deceased
-- member's finds, a list handed in by somebody who cannot enter it
-- himself. „Ich würde da zwei Felder draus machen":
--   mykis_determined  another finder, and I am the determiner;
--   mykis_foreign     everything else — I am neither finder nor determiner.
-- Whoever had the old tick keeps both, so nothing that went out before
-- stays home now. includes/mykis.php reads them (mykis_self).
ALTER TABLE map_users
    ADD mykis_determined TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'DGfM delivery: also finds of other finders that this account determined';

UPDATE map_users SET mykis_determined = mykis_foreign;
