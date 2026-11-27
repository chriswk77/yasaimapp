-- THE ORTSLAGE AT EVERY FIND (27.11.2026: „Kannst du die Ortslage auch an
-- den Fundort hängen? … Das ist ja vielleicht jetzt nicht nur für die DGFM
-- interessant"). The nearest field name, hamlet, wood or peak within a
-- kilometre, from map_place_names (0205) — shown after the municipality in
-- the point window, on the plates and in the search.
--
--   NULL  not looked up yet (the five-minute cron and every upload of the
--         Ortslagen file fill these)
--   ''    looked up, and nothing within a kilometre — mostly town centres
--
-- ONLY SHOWN, NOT IN THE PICTURES' NAMES (asked the same day: „Erst nur
-- anzeigen"). The folder and file names keep country, state and
-- municipality; whether the Ortslage joins them is decided once the names
-- have been seen.
--
-- THE CODE CAN GO LIVE FIRST: column_exists() asks, and until then no find
-- carries one.

ALTER TABLE map_points
    ADD COLUMN place_name VARCHAR(200) NULL DEFAULT NULL
        COMMENT 'Ortslage: nearest named place within 1 km (map_place_names); NULL = not looked up, empty = none'
        AFTER country;
