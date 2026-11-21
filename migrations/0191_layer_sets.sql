-- „EBENEN" — THE WORK ON A SET OF LAYERS, KEPT (11.11.2026).
--
-- He renders every focus stack three times in Helicon Focus (methods A, B
-- and C), lays the three over each other and paints the best of each into
-- one picture (assets/js/blend.js). Painting a subject in takes a while,
-- so the work must stand when he comes back: „Ja, den Arbeitsstand
-- sichern wir".
--
-- ONE ROW PER SET: which pictures of a find lie over each other, in which
-- order (the first is the ground), which are switched on, and which carry
-- a mask. `layers` is that list as JSON —
--   [{"key": "cloud:12", "shown": true, "mask": false}, …]
-- — the keys as the plates name pictures („photo:7", „cloud:12").
--
-- THE MASKS THEMSELVES ARE NOT HERE. A mask is a picture of several
-- megapixels; it lies as a PNG in the cloud of the picture it belongs to,
-- in „Bearbeitung" beside the sidecar, named „<picture>.ebene.png"
-- (includes/layer_sets.php) — where the retouching's patches lie, and
-- carried along by the same renaming, refiling and tidying.
--
-- A set is its maker's: only he sees and changes it (the tool is offered
-- on one's own finds). When the find or the account goes, the row goes.

CREATE TABLE map_layer_sets (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    point_id   INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL COMMENT 'who works on it — only he sees it',
    layers     TEXT         NOT NULL COMMENT 'the pictures, ground first, as JSON: key, shown, mask',
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_layer_sets_point (point_id, user_id),
    CONSTRAINT fk_layer_sets_user  FOREIGN KEY (user_id)  REFERENCES map_users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_layer_sets_point FOREIGN KEY (point_id) REFERENCES map_points (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
