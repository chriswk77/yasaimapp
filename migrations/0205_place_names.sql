-- PLACE NAMES FINER THAN THE MUNICIPALITY, for the MykIS column
-- BASIS_ortslage (27.11.2026: a coordinator missed the Ortslage; „bis
-- runter auf die Gemeinde können wir gehen", and below that OpenStreetMap
-- turned out to carry what nobody thought was free — in Hessen 14,442
-- field names, some 1,400 hamlets and single farms, 781 named woods and
-- 3,745 peaks).
--
--   map_place_names         one named place: a field name (place=locality),
--                           a hamlet or single farm, a named wood (its
--                           centre), a peak — kind says which
--   map_place_name_imports  each uploaded file: its date and how many names
--
-- THE NAMES COME AS A FILE, uploaded in the administration — built on a
-- desk computer by admin/place_names_build.php from OpenStreetMap's
-- Overpass, tile by tile (includes/place_names.php says why not by the
-- server). No find's coordinate is sent anywhere for it. OpenStreetMap's
-- data (ODbL) asks for its source to be named; the coordinator's mail
-- does.
--
-- THE CODE CAN GO LIVE FIRST: table_exists() asks, and until then the
-- column stays empty as it always was.

CREATE TABLE map_place_names (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kind VARCHAR(20) NOT NULL COMMENT 'locality | hamlet | isolated_dwelling | wood | peak',
    name VARCHAR(200) NOT NULL,
    lat  DECIMAL(8,5) NOT NULL,
    lon  DECIMAL(8,5) NOT NULL,
    PRIMARY KEY (id),
    KEY place_names_lat_lon (lat, lon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='named places from OpenStreetMap for the Ortslage';

CREATE TABLE map_place_name_imports (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    imported_at DATETIME NOT NULL,
    made        DATE NOT NULL COMMENT 'the day the file was built from OpenStreetMap',
    names       INT UNSIGNED NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='each uploaded file of place names';
