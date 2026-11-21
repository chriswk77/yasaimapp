-- Friends and working groups — the foundation for sharing (stage 1).
--
-- WHERE THIS LEADS. Sharing later happens via collection containers: a
-- container has members, and every member hangs their own lists into it.
-- One becomes a member as a friend or via a working group. This migration
-- only creates the relationships — containers and shares come in the next
-- stages.
--
-- ONE ROW PER PAIR, with direction. Two mirror rows (A→B and B→A) would be
-- more convenient to query but can drift apart — one half deleted, the
-- other forgotten. The direction also says who asked: as long as the
-- status is 'angefragt', only the one asked may accept.
--
-- DECLINING DELETES THE ROW, there is no status 'abgelehnt'. Whoever was
-- declined may ask again later — and nobody should accumulate in the
-- database a list of "who rejected whom" that no window ever shows.
--
-- NO FOREIGN KEYS — unlike the founding schema, which hangs points and
-- lists onto the account with ON DELETE CASCADE: when a friendship ends,
-- code has to run anyway (the pair's group memberships, see
-- freundschaft_entfernen()), and half database, half code would be the
-- split in which one half gets forgotten. mitglieder_loeschen() therefore
-- also clears these tables visibly by hand.
CREATE TABLE map_freunde (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    von_user_id    INT UNSIGNED NOT NULL COMMENT 'wer gefragt hat',
    an_user_id     INT UNSIGNED NOT NULL COMMENT 'wer gefragt wurde',
    status         ENUM('angefragt','angenommen') NOT NULL DEFAULT 'angefragt',
    erstellt_am    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    beantwortet_am DATETIME NULL,
    PRIMARY KEY (id),
    -- The counterpart (B→A) is caught by the code; a UNIQUE can only
    -- secure this direction.
    UNIQUE KEY paar (von_user_id, an_user_id),
    KEY an_mich (an_user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- THE OWNER IS NOT IN THE MEMBERS TABLE. He can neither leave nor be
-- removed — the group IS his. Whoever needs the members of a group in full
-- (the containers in stage 2) takes members + owner.
--
-- NO geloescht_am: nothing hangs on groups yet, final deletion with a
-- confirmation suffices. If shares hang on a group later, their ending
-- when the group is deleted is exactly the expected behavior.
CREATE TABLE map_gruppen (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    besitzer_user_id INT UNSIGNED NOT NULL,
    name             VARCHAR(100) NOT NULL,
    erstellt_am      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Two groups of the same owner with the same name would be
    -- indistinguishable in every later selection ("teilen mit …").
    UNIQUE KEY eindeutig (besitzer_user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Members come from the owner's circle of friends. If the friendship ends,
-- the membership ends with it (see freundschaft_entfernen() in
-- includes/freunde.php) — otherwise "Freundschaft beenden" would not mean
-- what it promises.
CREATE TABLE map_gruppen_mitglieder (
    gruppe_id      INT UNSIGNED NOT NULL,
    user_id        INT UNSIGNED NOT NULL,
    aufgenommen_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (gruppe_id, user_id),
    KEY mitglied (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
