-- FOREIGN EDITS OF A PICTURE (11.11.2026, „Fremdbearbeitungen").
--
-- A friend who may see a picture may add HIS OWN edits to it — Licht,
-- Farbe, Masken, Ausbessern, Zuschnitt — when its photographer allows it:
-- for a whole list by the recipient's tick „Bilder bearbeiten" (the right
-- `edit_photos` in the share's rights, no column needed), or for one
-- picture by its switch „Andere dürfen bearbeiten" (`edit_open` below).
-- „Geteilt ist Sehen und Arbeit, nie Besitz": an edit is its maker's work
-- and lies here, in a row of its own — never in the photographer's
-- `develop` or `develop_versions`, never in the sidecar in his cloud
-- (the same evening: „nur Datenbank"). Only when the
-- photographer makes one of them his valid edit is it copied into his own
-- edits, and from then on it is his.
--
-- A picture is a voucher photo (map_photos) or a cloud recording
-- (map_cloud_files) — „Bilder sind Bilder" —, named by kind and id as the
-- plates and the layer sets name them. The rows go with the picture
-- (picture_edits_forget, includes/picture_edits.php) and with the editor's
-- account (the key below). Whoever loses the right to a picture stops
-- seeing its edits, his own too; nothing is deleted for it.

CREATE TABLE map_picture_edits (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    picture_kind ENUM('photo', 'cloud') NOT NULL,
    picture_id   INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL COMMENT 'who made the edit — it is his',
    develop      MEDIUMTEXT   NULL COMMENT 'the settings as JSON, the shape of map_photos.develop',
    created_at   DATETIME     NOT NULL,
    updated_at   DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_picture_edits_picture (picture_kind, picture_id),
    KEY idx_picture_edits_user (user_id),
    CONSTRAINT fk_picture_edits_user FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The picture's own switch: everyone who may see it may add edits.
ALTER TABLE map_photos ADD COLUMN edit_open TINYINT(1) NOT NULL DEFAULT 0 AFTER develop_versions;
ALTER TABLE map_cloud_files ADD COLUMN edit_open TINYINT(1) NOT NULL DEFAULT 0 AFTER develop_versions;
