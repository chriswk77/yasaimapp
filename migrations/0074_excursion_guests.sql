-- Excursions, stage 2: the guest access.
--
-- THE ONE GUEST ACCOUNT (decided 23.08.2026): guest finds live in a
-- single collecting account, not with the leader — his expert stock
-- stays separate from amateur finds, and everything that needs steering
-- (time window, photo policy) hangs on the EXCURSION, so the accounts
-- are not needed to express it. The account is born locked: nobody logs
-- into it, it stays out of the user search, and its rows are written by
-- the guest page alone.
INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at, locked_at)
SELECT 'gast@exkursion.local', '', 'Exkursion', 'Gäste', 'user', NOW(), NOW()
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM map_users WHERE email = 'gast@exkursion.local');

-- WHO A GUEST IS: name and, voluntarily, an e-mail. The name fills the
-- finder; the e-mail is the recognition key for later attribution —
-- whoever withholds it thereby waives being attributed the points later
-- (the ruling: nobody is forced, and the waiver has a nameable
-- consequence instead of a hidden one).
CREATE TABLE IF NOT EXISTS map_excursion_guests (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    excursion_id INT UNSIGNED NOT NULL,
    name         VARCHAR(100) NOT NULL,
    email        VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_guest_excursion (excursion_id),
    KEY idx_guest_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- THE EXCURSION REFERENCE ON THE POINT is the carrying thought: the
-- right does not hang on the point and not on the account but on the
-- excursion — the leader may edit what carries HIS excursion's id, and
-- the later attribution finds a guest's points via this reference plus
-- the e-mail. NULL for every ordinary point; nothing existing changes.
ALTER TABLE map_points
    ADD COLUMN excursion_id INT UNSIGNED NULL,
    ADD COLUMN excursion_guest_id INT UNSIGNED NULL,
    ADD KEY idx_point_excursion (excursion_id);
