-- Named scale presets (28.08.2026 — „im Vorgedanken an die
-- Objektiv- und Kameragrößen": 10x, 20x, frei benannt).
--
-- WHAT A PRESET IS. A microscope camera always delivers the same pixel
-- grid, so one calibration per objective holds for EVERY photo from that
-- objective. The preset stores µm per ORIGINAL pixel under a name the
-- user chose; picking it in the viewer stamps the current photo with
-- that scale, and the free calibration remains the way to mint one.
--
-- µm/px AND NOT A LINE, and that is no break with the source-not-factor
-- rule: the photo keeps storing its line (materialised, see photos.php),
-- but the preset's own source WAS a line on some other photo — carrying
-- that photo's coordinates around would tie the preset to a picture that
-- may be long deleted. The factor is the preset's honest content.
--
-- PER USER, like everything about photos: presets are one's own working
-- set, not shared vocabulary. The unique key lets a re-save under the
-- same name replace instead of piling up „10x", „10x (2)", „10x (3)".
CREATE TABLE IF NOT EXISTS map_photo_scales (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    um_per_px  DOUBLE       NOT NULL COMMENT 'Mikrometer je ORIGINALPIXEL des geeichten Fotos',
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_scale_name (user_id, name),
    CONSTRAINT fk_photo_scale_user FOREIGN KEY (user_id)
        REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
