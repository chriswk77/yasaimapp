-- How the measuring overlay dresses (28.08.2026: „Skalenfarbe,
-- Schrift der Skalen und Linienfarbe beim Messen und Schrift etc. …
-- in die Verwaltung bei Einstellungen legen").
--
-- ON THE ACCOUNT, NOT ON THE DEVICE: whoever tunes the colours of their
-- measuring lines wants them on the phone in the woods and on the desk
-- alike — the same reasoning that keeps the scale PRESETS server-side
-- (0089). The device-local settings bundle (settings.js) is for what
-- belongs to one screen, and a drawing style does not.
--
-- ONE JSON COLUMN, NOT THREE. The set will drift with the tool (a colour
-- today, a dash pattern someday); every new knob as its own column would
-- be a migration per whim. The gate measure_style_write() in photos.php
-- validates both directions, like the scale JSON at 0088 — an unusable
-- value falls back to the defaults instead of drawing garbage.
ALTER TABLE map_users
    ADD COLUMN measure_style TEXT NULL
        COMMENT 'JSON: Farben und Schriftgröße des Mess-Overlays — NULL = Voreinstellung'
        AFTER quota_bytes;
