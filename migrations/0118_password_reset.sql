-- „Passwort vergessen" (23.09.2026, the day e-mail
-- confirmation went live: „bau Passwort vergessen für bestätigte
-- Adressen"). Until now a forgotten password locked its owner out for
-- good — the administration has no way to set one.
--
--   password_token          SHA-256 of the link's secret; the secret
--                           itself is only in the mail.
--   password_token_expires  one hour after sending — a link that sets the
--                           password lives shorter than one that merely
--                           confirms an address.
--
-- Separate columns, not the email_token ones: a pending address change
-- and a reset may run at the same time, and one link must never be able
-- to do the other's work.
ALTER TABLE map_users
    ADD COLUMN password_token         CHAR(64) NULL,
    ADD COLUMN password_token_expires DATETIME NULL,
    ADD KEY password_token (password_token);
