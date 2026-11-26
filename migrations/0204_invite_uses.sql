-- A CODE FOR SEVERAL PEOPLE (26.11.2026). The DGfM's state coordinators
-- get one invitation link in one letter, and each of them registers with
-- whatever address they like — a code bound to @dgfm-ev.de was weighed and
-- left: the coordinators write from role addresses, some would rather sign
-- up privately, and an address is not confirmed before the account works,
-- so a domain would have kept nobody out. So: a code that counts.
--
--   uses_left  how many more registrations the code lets in. NULL is the
--              code as it always was — one person, and the row goes. A
--              number counts down with each registration; at zero the row
--              goes like a spent single code (auth.php, account_create).
--
-- THE CODE CAN GO LIVE FIRST: column_exists() asks for the column; until
-- then the form does not offer the count and every code is single.

ALTER TABLE map_invites
    ADD COLUMN uses_left SMALLINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'registrations still possible — NULL = a single-use code' AFTER note;
