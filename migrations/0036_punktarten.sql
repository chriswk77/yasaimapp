-- The species of a point, one row per name.
--
-- WHY AT ALL. A species name today stands as text in map_points.zusatz and
-- its reference in zusatz_arten — both JSON. For input that is right:
-- the fields are freely nameable, every list has different ones, and what
-- somebody types should be preserved unfiltered.
--
-- For analysis it is no good. "All Boletus" would mean searching thousands
-- of JSON objects and guessing the genus from character strings; "how many
-- species do I know" could not be answered at all. With a few hundred
-- points that goes unnoticed, at eight thousand it is the wall.
--
-- TWO SOURCES, NOT ONE:
--
--   THE TITLE, when a list of the point carries ist_arten. That is where
--   the fungus stands — the main case. Passing it over would mean not
--   being able to use the table for exactly what it is meant for.
--
--   FIELDS with ist_arten: host plant, accompanying vegetation. Two
--   different names from two different groups in the same record — which
--   is why the attribute sits on the field and not only on the list.
--
-- feld = '' means: the title. A separate marker value for that would be a
-- second meaning of the same column; the empty string says "no field" and
-- incidentally sorts to the front.
--
-- DERIVED, NOT ENTERED. Every row here arises from zusatz, titel and
-- zusatz_arten and can be regenerated from them at any time. If it gets
-- lost, nothing is lost — that is the property that makes such a table
-- harmless.
--
-- user_id is included although it could be had via point_id. The same
-- decision as with the voucher photos (0031): "which genera do I have at
-- all" is a question about the whole holdings, and it should be answerable
-- without a join over the points.

CREATE TABLE map_point_arten (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id  INT UNSIGNED NOT NULL,
  point_id INT UNSIGNED NOT NULL,
  feld     VARCHAR(60)  NOT NULL COMMENT 'Feldname, leer = der Titel des Punktes',
  name     VARCHAR(190) NOT NULL COMMENT 'wie eingegeben, unveraendert',
  gattung  VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'aus der Namensliste, sonst erstes Wort',
  quelle   VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'dgfm | flechten | pflanzen | gbif | col',
  kennung  VARCHAR(64)  NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  -- The same name in the same field of a point is the same entry.
  UNIQUE KEY uq_map_point_arten (point_id, feld, name),
  KEY ix_map_point_arten_gattung (user_id, gattung),
  KEY ix_map_point_arten_name (user_id, name),
  KEY ix_map_point_arten_punkt (point_id),
  CONSTRAINT fk_map_point_arten_punkt
    FOREIGN KEY (point_id) REFERENCES map_points (id) ON DELETE CASCADE,
  CONSTRAINT fk_map_point_arten_user
    FOREIGN KEY (user_id) REFERENCES map_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
