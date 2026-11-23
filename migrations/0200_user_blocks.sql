-- BLOCKING A PERSON, AND THE RULES ONE ACCEPTS (23.11.2026), for the Play
-- review: Google wants users to be able to block and report each other
-- wherever they can write to one another, and the rules accepted before
-- anybody shares anything.
--
-- „Weil man auch eine Freundschaft beenden kann und sagen kann, mit dem
-- will ich nichts mehr zu tun haben, damit ist er blockiert." So BLOCKING
-- IS ENDING A FRIENDSHIP, under one button called „Blockieren" — what
-- friendship_remove() already did, plus this row, which remembers it:
-- until now the other could send a new request the next minute.
--
--   user_id          who blocked
--   blocked_user_id  whom — neither can request the other, and neither
--                    finds the other in the search, while the row stands
--
-- One row per direction; lifting the block is deleting it. Both people
-- going takes it along (CASCADE).
--
-- REPORTING NEEDS NO TABLE: a report is a mail to the administrators
-- (friends_report in includes/friends.php), who answer it with the lock
-- in Mitglieder.
--
--   terms_accepted_at  when the account accepted the rules (pages/terms.php).
--                      Asked at the registration; NULL for every account
--                      older than this migration.
--
-- THE CODE CAN GO LIVE FIRST: user_blocks_ready() asks for the table and
-- column_exists() for the column; until then the button ends a friendship
-- as before and the registration stores no date.

CREATE TABLE map_user_blocks (
    user_id         INT UNSIGNED NOT NULL COMMENT 'who blocked',
    blocked_user_id INT UNSIGNED NOT NULL COMMENT 'whom',
    created_at      DATETIME     NOT NULL,
    PRIMARY KEY (user_id, blocked_user_id),
    KEY idx_user_blocks_blocked (blocked_user_id),
    CONSTRAINT fk_user_blocks_user    FOREIGN KEY (user_id)         REFERENCES map_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_blocks_blocked FOREIGN KEY (blocked_user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE map_users
    ADD COLUMN terms_accepted_at DATETIME NULL;
