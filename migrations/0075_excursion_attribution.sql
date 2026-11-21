-- Excursions, stage 7: the attribution.
--
-- A guest who registers later gets his finds — NOT as a data move but as
-- a TIE (the concept's ruling of 23.08.2026: "immer nur ein Verweis, für
-- alle gleich"): the guest row learns which account it belongs to, the
-- finds stay in the guest account, and everything follows from the tie —
-- the participant sees and tends his own excursion finds, the later
-- report knows who found what. Claimed on EVERY login via the e-mail the
-- guest chose to leave: that also catches the participant who already
-- had an account, and whoever withheld the e-mail waived exactly this.
ALTER TABLE map_excursion_guests
    ADD COLUMN user_id INT UNSIGNED NULL,
    ADD KEY idx_guest_user (user_id);
