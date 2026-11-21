-- The account learns its language (28.08.2026: „damit wir
-- multilingual werden können"). NULL means AUTOMATIC — the browser's
-- Accept-Language decides, which is the right default for everyone who
-- never chose: a German browser gets the German app exactly as before
-- this column. The value is the catalog's file name under
-- includes/lang/ ('en'); German needs no file, the source text IS the
-- German sentence (see includes/i18n.php for the whole architecture).
ALTER TABLE map_users
    ADD COLUMN lang VARCHAR(5) NULL
        COMMENT 'Sprachwahl des Kontos — NULL = automatisch nach Browser'
        AFTER measure_style;
