-- Recorded tracks (25.08.2026 — the recorder app's first step).
--
-- WHY A TABLE AND NOT POINTS. A track is one thing with thousands of
-- positions, a find is one position with a name. Poured into map_points, a
-- single walk would drown every list, every filter and every marker layer
-- in a few thousand rows that nobody wants to see individually.
--
-- THE POSITIONS LIE IN ONE COLUMN, as JSON, exactly like map_points.outline
-- does with its corners. A row per position would mean a million-row table
-- within a season, with an index that costs more than it earns: nobody ever
-- asks for a single position of a track — one always wants the whole line.
-- The derived numbers (distance, duration, count, bounding box) stand
-- beside it as columns, so a listing needs no JSON parsing at all.
--
-- client_id IS MINTED ON THE DEVICE, like the one on map_points (migration
-- 0020) and like a tour's id (0079). A track begins where there is no
-- reception; an id fetched from the server would need a provisional one
-- beside it and a rewrite later. It is at the same time the idempotence
-- key: the app may send the same walk twice — after a lost connection, or
-- because it grew — and the second send REPLACES the first instead of
-- creating a twin.
CREATE TABLE IF NOT EXISTS map_tracks (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    client_id     CHAR(36)     NOT NULL COMMENT 'auf dem Gerät vergeben, zugleich Schlüssel gegen Doppelablage',
    name          VARCHAR(200) NULL,
    device        VARCHAR(100) NULL COMMENT 'welches Gerät aufgezeichnet hat — aus der Marke',
    started_at    DATETIME     NULL,
    ended_at      DATETIME     NULL,
    point_count   INT UNSIGNED NOT NULL DEFAULT 0,
    distance_m    INT UNSIGNED NULL COMMENT 'auf dem Server aus den Positionen gerechnet, nicht dem Gerät geglaubt',
    lat_min       DECIMAL(9,6) NULL,
    lat_max       DECIMAL(9,6) NULL,
    lon_min       DECIMAL(9,6) NULL,
    lon_max       DECIMAL(9,6) NULL,
    positions     LONGTEXT     NOT NULL COMMENT 'JSON: [[lat, lon, ele_m|null, t_ms, acc_m|null], …]',
    created_at    DATETIME     NOT NULL,
    updated_at    DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_track_client (user_id, client_id),
    KEY idx_track_user_time (user_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
