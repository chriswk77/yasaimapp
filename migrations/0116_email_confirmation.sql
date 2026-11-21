-- E-mail confirmation (23.09.2026: „mach mir eine
-- E-Mail-Anmeldungsbestätigung"), after the security review of the same
-- day found what an unconfirmed address could do: an account took any
-- address it liked, and the address decided whose excursion finds it was
-- given.
--
--   email_confirmed_at   when the CURRENT address was confirmed; NULL
--                        means not yet. An account is usable before —
--                        what hangs on it is only what trusts the address
--                        (excursion_claim).
--   email_pending        a new address asked for in the settings; it takes
--                        effect only once its link is followed. Until then
--                        the old one stays the login.
--   email_token          SHA-256 of the link's secret — the secret itself
--                        is only in the mail, like every token here.
--   email_token_expires  48 hours after sending.
--
-- EVERY EXISTING ACCOUNT COUNTS AS CONFIRMED, with its creation date:
-- they are the handful of known people who have used it for weeks, and a
-- sudden „please confirm" for all of them would be ceremony, not safety.
ALTER TABLE map_users
    ADD COLUMN email_confirmed_at  DATETIME     NULL,
    ADD COLUMN email_pending       VARCHAR(190) NULL,
    ADD COLUMN email_token         CHAR(64)     NULL,
    ADD COLUMN email_token_expires DATETIME     NULL,
    ADD KEY email_token (email_token);

UPDATE map_users SET email_confirmed_at = created_at WHERE email_confirmed_at IS NULL;
