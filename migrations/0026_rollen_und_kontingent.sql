-- Roles instead of a checkmark, and room for files per user.
--
-- UNTIL NOW THERE WERE TWO KINDS OF PERSON: ist_admin set or not. That
-- carried as long as there was a single user. As soon as more join, the
-- middle is missing — and a second checkmark next to it would be the wrong
-- way: two booleans are four states, of which two make no sense.
--
--   admin      sees the server administration, invites, applies migrations
--   benutzer   the rule: own points, own lists, own files
--   gast       created, but without a meaning of its own yet
--
-- THE ROLE "gast" IS DELIBERATELY EMPTY. It stands ready so that later it
-- does not have to be squeezed retroactively into a column that already
-- exists. What a guest may do is not yet decided; until then nothing
-- distinguishes them from a user. Leaving that honestly as it is beats
-- inventing rules for them that nobody has needed.
--
-- WHY ist_admin DISAPPEARS instead of staying alongside: two statements
-- about the same thing drift apart. There is exactly one question — what
-- role does this person have —, so there is exactly one column.
--
-- QUOTA: NULL means unlimited, not zero. That is the default value, and it
-- is chosen on purpose: a limit nobody has set is no limit. Whoever needs
-- one gets it individually.

ALTER TABLE map_users
    ADD rolle ENUM('admin', 'benutzer', 'gast') NOT NULL DEFAULT 'benutzer'
        COMMENT 'was dieser Mensch darf' AFTER name,
    ADD kontingent_bytes BIGINT UNSIGNED NULL
        COMMENT 'Grenze für eigene Dateien; NULL = unbegrenzt' AFTER rolle;

UPDATE map_users SET rolle = 'admin' WHERE ist_admin = 1;

ALTER TABLE map_users DROP COLUMN ist_admin;

-- The invitation determines the role, not the registration form.
--
-- Otherwise somebody would have to be promoted by hand after joining, and
-- between joining and promotion an account with the wrong rights would
-- stand there. Whoever invites knows anyway whom they are inviting.
ALTER TABLE map_invites
    ADD rolle ENUM('admin', 'benutzer', 'gast') NOT NULL DEFAULT 'benutzer'
        COMMENT 'welche Rolle das eingelöste Konto bekommt' AFTER erstellt_von;
