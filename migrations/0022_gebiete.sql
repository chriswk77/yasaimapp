-- Offline areas: the outline belongs on the server, the tiles on the device.
--
-- That was the flaw in the first version's thinking: both lived in the
-- browser. With that, an area was only known on the device it was drawn on
-- — and it gets drawn at the desk with the mouse, while loading has to
-- happen where one will later stand: on the phone.
--
-- The split follows from who owns what:
--
--   THE OUTLINE belongs to the user. It is small, it rarely changes, and it
--   should be visible on every one of their devices. So it goes here.
--
--   THE TILES belong to the device. They are hundreds of megabytes, they
--   hang on its storage, and putting them on the server would mean
--   transferring them twice. So they stay in the browser.
--
-- How a device recognizes whether it has already loaded an area is thereby
-- answered too: it looks in its own storage. No bookkeeping about it that
-- would have to be synced between devices and then drifts apart.
--
-- gueltig_bis also lives here: when an area expires, it disappears on all
-- devices, and each one clears away its own tiles.

CREATE TABLE map_gebiete (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  client_id   CHAR(36)     NULL COMMENT 'vom Gerät vergeben, macht das Anlegen wiederholbar',
  name        VARCHAR(60)  NOT NULL,
  ecken       JSON         NOT NULL COMMENT 'Polygon als [[lat,lon], ...]',
  zoom_bis    TINYINT UNSIGNED NOT NULL DEFAULT 18,
  gueltig_bis DATE         NULL COMMENT 'danach räumt sich das Gebiet überall weg',
  erstellt_am  DATETIME    NOT NULL,
  geaendert_am DATETIME    NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_map_gebiete_client (user_id, client_id),
  KEY ix_map_gebiete_user (user_id),
  CONSTRAINT fk_map_gebiete_user
    FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
