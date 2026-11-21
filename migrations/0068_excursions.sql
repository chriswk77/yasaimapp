-- Excursions, stage 1: create and manage.
--
-- THE RIGHT HANGS ON THE EXCURSION, not on the point and not on the
-- account (the concept of 23.08.2026): the leader may edit what
-- belongs to HIS excursion, the time window says when anything may come
-- into being at all, and the later attribution of guest finds works via
-- the same reference plus an e-mail address. This table is that anchor.
-- The point reference, the guest account and the QR join page follow in
-- later migrations — but the JOIN TOKEN is born here: it is the
-- excursion's identity towards its guests, and an identity that can be
-- missing would need a second code path everywhere it is read.
CREATE TABLE IF NOT EXISTS map_excursions (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    leader_user_id INT UNSIGNED NOT NULL,
    name           VARCHAR(200) NOT NULL,
    -- The time window (mostly a day, sometimes a weekend). Inside it
    -- guests may collect points; with its end the QR access ends too,
    -- without any revocation step.
    starts_at      DATETIME NOT NULL,
    ends_at        DATETIME NOT NULL,
    join_token     CHAR(32) NOT NULL,
    photos_allowed TINYINT(1) NOT NULL DEFAULT 1,
    -- NULL means: the operator's default applies. A number is the
    -- leader's own measure for the whole excursion — photos of guest
    -- finds count against the EXCURSION, not against a person.
    photo_limit    INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_excursion_token (join_token),
    KEY idx_excursion_leader (leader_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
