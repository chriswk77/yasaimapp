-- The delivery of finds to the DGfM's state coordinators for MykIS
-- (25.09.2026, after talking to the
-- coordinator for Hessen: „ein- bis zweimal im Jahr ist vollkommen
-- ausreichend"). Twice a year, early in June and early in December, a
-- cron job sends every coordinator who agreed the own finds of every
-- finder who agreed — each find once, coarsened to the MTB quarter
-- (includes/mykis.php says what goes into the sheet and what stays out).
--
-- THE FINDER'S CONSENT. The sheet carries names, and the mail carries the
-- finder's address so the coordinator can ask back; nothing of that
-- leaves without the finder's own tick, and the tick can only be set
-- with a confirmed address („man kann erst einwilligen, wenn
-- die E-Mail-Adresse bestätigt ist"). NULL is no.
ALTER TABLE map_users
    ADD mykis_consent_at DATETIME NULL COMMENT 'agreed to have own finds delivered to the DGfM mapping; NULL = no';

-- THE COORDINATORS, one row per state as the DGfM lists them on
-- dgfm-ev.de/de/naturschutz/landeskoordinatoren-fuer-pilzkartierung
-- (read 25.09.2026). The addresses are the society's ROLE addresses
-- (pilze-hessen@dgfm-ev.de), not private ones. A coordinator who looks
-- after two states stands in two rows with the same address — one mail
-- per finder goes to an address, whatever rows share it.
--
-- 'part' SPLITS A STATE: Baden-Württemberg has a coordinator for the
-- south and another for the north (together with Rheinland-Pfalz). Where
-- the line runs is not settled yet, so finds from Baden-Württemberg wait
-- until it is (mykis_coordinator_for).
--
-- 'consented_at' IS THE ADMINISTRATOR'S TICK: the maintainer writes to every
-- coordinator and ticks the ones who agreed to receive the sheets
-- automatically. Nobody unticked gets a mail.
CREATE TABLE map_mykis_coordinators (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    region       CHAR(3)      NOT NULL COMMENT 'state code as on map_points.region',
    part         VARCHAR(20)  NULL COMMENT 'where a state is split: Nord, Süd',
    name         VARCHAR(120) NOT NULL,
    deputy       VARCHAR(120) NULL,
    email        VARCHAR(190) NOT NULL,
    consented_at DATETIME     NULL COMMENT 'agreed to receive the sheets automatically; NULL = no',
    PRIMARY KEY (id),
    KEY region (region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO map_mykis_coordinators (region, part, name, deputy, email) VALUES
    ('BAY', NULL,   'Peter Karasch',                  NULL,              'pilze-bayern@dgfm-ev.de'),
    ('BAW', 'Süd',  'Joshua Ferenczy',                NULL,              'pilze-bawue@dgfm-ev.de'),
    ('BAW', 'Nord', 'Dagmar Gödert',                  NULL,              'pilze-rlp@dgfm-ev.de'),
    ('RLP', NULL,   'Dagmar Gödert',                  NULL,              'pilze-rlp@dgfm-ev.de'),
    ('BLN', NULL,   'Dr. Martin Schmidt',             NULL,              'pilze-berlin-brandenburg@dgfm-ev.de'),
    ('BRB', NULL,   'Dr. Martin Schmidt',             NULL,              'pilze-berlin-brandenburg@dgfm-ev.de'),
    ('HAM', NULL,   'Linus Koch',                     'Maren Kamke',     'pilze-hamburg@dgfm-ev.de'),
    ('HES', NULL,   'Harald Zühlsdorf',               NULL,              'pilze-hessen@dgfm-ev.de'),
    ('MVP', NULL,   'Dr. Oliver Duty',                'Benno Westphal',  'pilze-mv@dgfm-ev.de'),
    ('NDS', NULL,   'Axel Schilling',                 'Jörg Albers',     'pilze-niedersachsen-bremen@dgfm-ev.de'),
    ('BRM', NULL,   'Axel Schilling',                 'Jörg Albers',     'pilze-niedersachsen-bremen@dgfm-ev.de'),
    ('NRW', NULL,   'Karl Wehr',                      NULL,              'pilze-nrw@dgfm-ev.de'),
    ('SAL', NULL,   'Dr. Johannes Schmitt',           'Dirk Gerstner',   'pilze-saarland@dgfm-ev.de'),
    ('SAC', NULL,   'Prof. Dr. Hans-Jürgen Hardtke',  'Frank Dämmrich',  'pilze-sachsen@dgfm-ev.de'),
    ('SAH', NULL,   'Ulla Täglich',                   'Gunnar Hensel',   'pilze-sachsen-anhalt@dgfm-ev.de'),
    ('SLH', NULL,   'Tanja Böhning',                  'Maren Kamke',     'pilze-schleswig-holstein@dgfm-ev.de'),
    ('THU', NULL,   'Stefan Born',                    'Frank Langguth',  'pilze-thueringen@dgfm-ev.de');

-- EACH FIND ONCE („jeder Fund nur einmal"). A row here means
-- the find went out, to whom and when; the next run passes it by. No
-- foreign key to the point: a find deleted after it was delivered is
-- still in the coordinator's database, and forgetting that it went out
-- would send it again the day it is restored.
CREATE TABLE map_mykis_deliveries (
    point_id       INT UNSIGNED NOT NULL,
    coordinator_id INT UNSIGNED NOT NULL,
    delivered_at   DATETIME     NOT NULL,
    PRIMARY KEY (point_id),
    KEY coordinator_id (coordinator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
