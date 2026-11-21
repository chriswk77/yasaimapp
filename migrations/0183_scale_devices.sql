-- Devices for the scale presets (02.11.2026: „für die
-- Maßstabvorlagen können wir da auch Sets anlegen … normales Mikroskop,
-- Reisemikroskop, Stereolupe … dass man vorher erstmal sein Mikroskop-Set
-- wählt und dann die entsprechenden Objektive vorgeschlagen kriegt").
--
-- WHAT A DEVICE IS. A name that gathers presets, and nothing else — the
-- Mappe of the presets: no settings, no camera, no share. Each preset
-- keeps its µm per pixel and its calibration image as before. The word is
-- „Gerät" (his call): a stereo magnifier is no microscope, and „Set" is
-- no word for a beginner.
--
-- THE NAME IS UNIQUE PER DEVICE NOW, no longer per account: „40x" on the
-- lab microscope and „40x" on the travel one are two objectives. The new
-- key starts with user_id, so it also carries the user foreign key once
-- the old one is gone.
--
-- EVERY PRESET OF TODAY goes into one device „Mikroskop" per account (his
-- call), to be renamed at will. Pictures already stamped keep their scale:
-- the stamp materialised the factor and never depended on the preset.
CREATE TABLE IF NOT EXISTS map_scale_devices (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_scale_device_name (user_id, name),
    CONSTRAINT fk_scale_device_user FOREIGN KEY (user_id)
        REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE map_photo_scales
    ADD COLUMN device_id INT UNSIGNED NULL AFTER user_id;

INSERT INTO map_scale_devices (user_id, name, created_at)
    SELECT DISTINCT user_id, 'Mikroskop', NOW() FROM map_photo_scales;

UPDATE map_photo_scales s
    JOIN map_scale_devices d ON d.user_id = s.user_id AND d.name = 'Mikroskop'
    SET s.device_id = d.id;

-- NOT NULL once filled: a NULL in a unique key never collides, and „40x"
-- saved twice would breed two rows instead of replacing one.
ALTER TABLE map_photo_scales
    MODIFY device_id INT UNSIGNED NOT NULL,
    ADD UNIQUE KEY idx_scale_device_preset (user_id, device_id, name),
    ADD CONSTRAINT fk_photo_scale_device FOREIGN KEY (device_id)
        REFERENCES map_scale_devices (id) ON DELETE CASCADE;

ALTER TABLE map_photo_scales DROP INDEX idx_scale_name;
