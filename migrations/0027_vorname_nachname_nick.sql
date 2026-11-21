-- First and last name separated, plus a nick.
--
-- UNTIL NOW THERE WAS ONE FIELD "name" into which everyone wrote whatever
-- they wanted. That carries as long as one is alone. As soon as more join,
-- both are needed:
--
--   FIRST AND LAST NAME  Who is this? For administration, for records, for
--                        the question of whom a find is attributable to.
--   NICK                 What do they call themselves? Short, unique,
--                        self-chosen — and good enough for logging in.
--
-- LOGIN WORKS WITH EITHER, e-mail or nick. The two cannot be confused,
-- because a nick may not contain an @; the query thus does not have to
-- guess which of the two was meant.
--
-- THE NICK IS UNIQUE. Without that it would be useless for logging in —
-- two people with the same nick, and the query finds two accounts.
--
-- WHAT BECOMES OF THE OLD name: it moves into the first name, and the nick
-- arises from the part of the e-mail before the @. Both are a guess, not a
-- statement — which is why both can be changed in the user administration.
-- A last name is not invented; the field stays empty until somebody fills
-- it.

ALTER TABLE map_users
    ADD vorname  VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'Rufname'     AFTER passwort_hash,
    ADD nachname VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'Familienname' AFTER vorname,
    ADD nick     VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'kurz, eindeutig, zum Anmelden' AFTER nachname;

UPDATE map_users SET vorname = name WHERE vorname = '';

-- "anna.musterfrau@example.com" becomes "annamusterfrau". Everything that
-- is not a letter, a digit, a dash or an underscore drops out — otherwise
-- a dot would stand in the nick, and it does not belong there.
UPDATE map_users
   SET nick = LOWER(REGEXP_REPLACE(SUBSTRING_INDEX(email, '@', 1), '[^A-Za-z0-9_-]', ''))
 WHERE nick = '';

-- For the unlikely case that nothing was left over from that.
UPDATE map_users SET nick = CONCAT('benutzer', id) WHERE nick = '';

ALTER TABLE map_users
    DROP COLUMN name,
    ADD UNIQUE KEY nick (nick);
