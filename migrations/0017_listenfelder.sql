-- Custom fields per list.
--
-- A find list needs different things than a list of parking spots. Instead
-- of cluttering the application with fields most people never need, every
-- list defines for itself what it asks for in addition to the note.
--
-- WHY THE VALUES AS JSON ON THE POINT AND NOT IN A TABLE OF THEIR OWN
-- The obvious form would be map_punkt_feld (punkt_id, feld_id, wert). It
-- fails on the case that is expressly supposed to exist here: a point lies
-- in several lists, and what gets displayed is the union of all fields.
-- If two lists both define a "Substrat", those would be two feld_id and
-- thus two storage slots for one field that the user sees once and fills
-- in once. Which of the two values then applies cannot be answered.
--
-- That is why the field name is the key: zusatz contains {"Substrat": "Buche"}.
-- The union across several lists is thereby simply a union of names, and a
-- value survives even when the point changes lists or the field definition
-- moves.
--
-- The price: filtering by field values only works via JSON paths. As long
-- as nobody does that, it is no price.

CREATE TABLE map_list_felder (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  list_id    INT UNSIGNED NOT NULL,
  name       VARCHAR(60)  NOT NULL COMMENT 'zugleich Beschriftung und Schlüssel im JSON',
  typ        VARCHAR(16)  NOT NULL DEFAULT 'text' COMMENT 'text | mehrzeilig',
  sortierung SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  -- The same field name twice in one list would only be confusing: both
  -- would point to the same value.
  UNIQUE KEY uq_map_list_felder (list_id, name),
  CONSTRAINT fk_map_list_felder_liste
    FOREIGN KEY (list_id) REFERENCES map_lists (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE map_points
    ADD zusatz JSON NULL COMMENT 'Werte der Listenfelder, nach Feldnamen' AFTER notiz;

-- For the species list, create right away the field this was all about.
INSERT INTO map_list_felder (list_id, name, typ, sortierung)
SELECT l.id, 'Substrat / Mykorrhiza-Partner / Wirt', 'text', 0
  FROM map_lists l
 WHERE l.ist_arten = 1
   AND l.geloescht_am IS NULL
   AND NOT EXISTS (
       SELECT 1 FROM map_list_felder f
        WHERE f.list_id = l.id AND f.name = 'Substrat / Mykorrhiza-Partner / Wirt'
   );
