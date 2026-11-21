-- THE MARGIN AROUND THE CUT-OUTS, PER FIND (07.10.2026).
--
-- „Die Randeinstellungen … Global für alle Arten und alle
-- Messungen, oder individuell? Denn die bessere Lösung wäre individuell
-- … geräteübergreifend." Until now the margin lived in the account's
-- measure_style per layer — an Ascobolus' ornamented spores at 250 %
-- set every other find's spores to 250 % too.
--
-- ONE ROW PER PERSON, FIND AND LAYER: the layer as the statistics name
-- it — „Sporen", or a part's tab „Sporen · Keimporus". Per person,
-- because the margin is how one LOOKS at a find, and a co-owner must not
-- move another's view. A find without a row of its own falls back to the
-- account's value per layer, then to the stock margin — so nothing that
-- was set before changes. Read only when a find's pictures are opened;
-- the account's JSON, loaded with every page, does not grow by it.

CREATE TABLE map_measure_air (
    user_id    INT UNSIGNED      NOT NULL,
    point_id   INT UNSIGNED      NOT NULL,
    label      VARCHAR(40)       NOT NULL COMMENT 'the layer, or „<layer> · <part>" for a part measure',
    air        SMALLINT UNSIGNED NOT NULL COMMENT 'percent of the measured size, 100–300',
    updated_at DATETIME          NOT NULL,
    PRIMARY KEY (user_id, point_id, label),
    KEY idx_measure_air_point (point_id),
    CONSTRAINT fk_measure_air_user  FOREIGN KEY (user_id)  REFERENCES map_users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_measure_air_point FOREIGN KEY (point_id) REFERENCES map_points (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
