-- The nick is abolished.
--
-- It was second login identifier and display name at once (migration 0027)
-- — for a handful of people who know each other, a second identity without
-- benefit. Login is by e-mail address, displayed are first and last name
-- (fallback: the e-mail address), see benutzer_anzeigename() in
-- includes/auth.php.
--
-- The code already runs without the column before this migration: no
-- INSERT names it anymore (it has DEFAULT ''), no query reads it. The only
-- gap until it is applied: the UNIQUE key tolerates only ONE account with
-- an empty nick — if somebody creates two new accounts in this window, the
-- second fails. With four known users the window does not matter.

ALTER TABLE map_users DROP KEY nick;
ALTER TABLE map_users DROP COLUMN nick;
