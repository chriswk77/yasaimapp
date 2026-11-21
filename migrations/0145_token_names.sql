-- A name of one's own for a signed-in browser (28.09.2026: „ich
-- habe hier halt viele Anmeldungen von Android … und weiß nicht, ob das auf
-- meinem Tablet war oder auf meinem Handy"). A browser says little about
-- itself; since 2023 Chrome on Android sends „Android 10; K" for every
-- phone and tablet alike. So the list reads the device column for the
-- automatic label („Chrome auf Android-Tablet") and this column for the
-- name the user gave with the pencil; NULL means the automatic one.
--
-- A paired app needs none of it: its name lives in the device column
-- („App: <name>") since pairing, and renaming rewrites that
-- (settings_content.php, device_rename).
ALTER TABLE map_tokens
    ADD name VARCHAR(60) NULL DEFAULT NULL
        COMMENT 'the name the user gave a signed-in browser; NULL = the automatic label'
        AFTER device;
