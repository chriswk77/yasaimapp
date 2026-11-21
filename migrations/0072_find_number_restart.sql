-- The find-number counter can restart with each new date section
-- (23.08.2026): a pattern that carries the day already makes
-- its numbers unique per day — "TT.MM.JJJJ-NN" may then count 1, 2, 3
-- anew every morning. The finest date part of the pattern decides the
-- section: day, month, or year.
--
-- find_counter_period remembers WHICH section the counter currently
-- counts in ('2026', '2026-08' or '2026-08-23'); the mint compares it to
-- the find date's section and resets to 1 on a change — atomically, in
-- the same UPDATE that draws the number (find_number_mint). NULL means:
-- never minted under this rule yet.

ALTER TABLE map_users
    ADD COLUMN find_number_restart TINYINT(1) NOT NULL DEFAULT 0 AFTER find_counter,
    ADD COLUMN find_counter_period VARCHAR(10) NULL AFTER find_number_restart;
