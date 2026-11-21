-- Containers get birth filters: a time span, an area, title rules
-- (contains / does not contain, all joined with AND — like the rules of an
-- iTunes playlist). They are set ONCE at creation and never touched again:
-- a filter that can change later would be a promise built on sand — "I
-- only shared the excursion list" must still hold tomorrow. Hence a single
-- JSON column instead of tables of their own: it is a snapshot, not a
-- relationship. The area, too, lies within it as a COPY of its corners —
-- the source area may change or disappear afterwards without the filter
-- following along.
--
-- Structure of the JSON:
--   { "zeit":     {"von": "2026-08-13", "bis": "2026-08-13"},   each optional
--     "gebiet":   {"name": "...", "ecken": [[lat, lon], ...]},
--     "begriffe": [{"art": "enthaelt"|"enthaelt_nicht", "text": "..."}] }
-- NULL means: no filter, the container shares everything (as before).
--
-- No CHECK on json_valid: the live server speaks MariaDB, and the column
-- is only ever written by container_anlegen() anyway.

ALTER TABLE map_container
    ADD COLUMN filter TEXT NULL
    COMMENT 'Geburtsfilter als JSON (zeit/gebiet/begriffe), NULL = alles; nie editierbar';
