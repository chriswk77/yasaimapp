-- Co-leaders of an excursion (stage 1b, the call of 22.08.2026:
-- "dass wir zwei Exkursionsleiter haben").
--
-- THE CREATOR STAYS THE MAIN LEADER — the column leader_user_id on the
-- excursion. Only he deletes the excursion and appoints or removes
-- co-leaders, the same division of roles as with a container's owner. A
-- row HERE grants everything professional: editing the excursion, later
-- tidying its points and photos, and showing the QR code — the join
-- token belongs to the excursion, not to a device, so every leader's
-- phone shows the same one.
CREATE TABLE IF NOT EXISTS map_excursion_leaders (
    excursion_id INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    added_at     DATETIME NOT NULL,
    PRIMARY KEY (excursion_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
