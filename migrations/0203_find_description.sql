-- THE FIND FIELD „BEMERKUNG" IS CALLED „FUNDBESCHREIBUNG" (26.11.2026:
-- „Notiz und Bemerkung, die Bezeichnungen sind ja ziemlich ähnlich" — the
-- text one writes FOR OTHERS about the find and its situation, the one the
-- Fundbuch publishes; includes/find_fields.php says the rest).
--
-- A FIELD'S NAME IS ITS STORED KEY, so this moves data:
--
--   map_points.extra           "Bemerkung" → "Fundbeschreibung"
--   map_points.field_times     "extra.Bemerkung" → "extra.Fundbeschreibung"
--   map_points.original        the rescue anchors, the same keys
--   map_point_overwritten      field 'extra.Bemerkung' → 'extra.Fundbeschreibung'
--   rights of list shares      "field:Bemerkung" → "field:Fundbeschreibung"
--   map_list_fields            a species list's own old field „Bemerkung",
--                              hidden behind the fixed one since 09.11.2026,
--                              goes: its values were the fixed field's all
--                              along (one key) and move with them
--
-- NOT EVERY „BEMERKUNG" IS THE FIND FIELD. A list WITHOUT species may have
-- a field of its own with that name, its values under the same key — they
-- stay. So a point in such a list keeps its „Bemerkung"; if it lies in a
-- species list as well, it gets the copy beside it. And a list with a
-- field of its own called „Fundbeschreibung" is left alone altogether: its
-- key is no copy.
--
-- THE CODE WENT LIVE FIRST and hands the old key on under the new one
-- until this file is in the ledger (find_extra_carry). Whoever saved a
-- find in between sent BOTH keys back — the new one is then the newer word
-- and wins. A list without species may have picked up such a copy the same
-- way; where it still equals its own „Bemerkung", it goes again.
--
-- Written for MySQL 8 and MariaDB alike: the value travels as unquoted
-- text, which both store as a JSON string (JSON_EXTRACT's result would be
-- quoted twice on MariaDB).

-- 1. The copy, where the fixed field is meant: the point lies in a species
--    list, or in no list that has a field of its own by the old name.
UPDATE map_points p
   SET p.extra = JSON_SET(p.extra, '$.Fundbeschreibung', JSON_UNQUOTE(JSON_EXTRACT(p.extra, '$.Bemerkung')))
 WHERE p.extra LIKE '%"Bemerkung"%'
   AND JSON_TYPE(JSON_EXTRACT(p.extra, '$.Bemerkung')) = 'STRING'
   AND NOT JSON_CONTAINS_PATH(p.extra, 'one', '$.Fundbeschreibung')
   AND NOT EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_list_fields f ON f.list_id = pl.list_id
                    WHERE pl.point_id = p.id AND f.name = 'Fundbeschreibung')
   AND (EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_lists l ON l.id = pl.list_id
                 WHERE pl.point_id = p.id AND l.is_species = 1)
        OR NOT EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_lists l ON l.id = pl.list_id
                        JOIN map_list_fields f ON f.list_id = l.id
                        WHERE pl.point_id = p.id AND l.is_species = 0 AND f.name = 'Bemerkung'));

-- 2. Where the old key goes (the same condition in 2a–2d, and in 3 at
--    last): both keys there, no list of the point owns either name.
--    Its field time and its rescue anchor follow it first.
UPDATE map_points p
   SET p.field_times = REPLACE(p.field_times, '"extra.Bemerkung":', '"extra.Fundbeschreibung":')
 WHERE p.field_times LIKE '%"extra.Bemerkung":%'
   AND p.field_times NOT LIKE '%"extra.Fundbeschreibung":%'
   AND p.extra LIKE '%"Bemerkung"%'
   AND JSON_CONTAINS_PATH(p.extra, 'all', '$.Bemerkung', '$.Fundbeschreibung')
   AND NOT EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_lists l ON l.id = pl.list_id
                    JOIN map_list_fields f ON f.list_id = l.id
                    WHERE pl.point_id = p.id AND l.is_species = 0 AND f.name = 'Bemerkung')
   AND NOT EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_list_fields f ON f.list_id = pl.list_id
                    WHERE pl.point_id = p.id AND f.name = 'Fundbeschreibung');

UPDATE map_points p
   SET p.original = REPLACE(p.original, '"extra.Bemerkung":', '"extra.Fundbeschreibung":')
 WHERE p.original LIKE '%"extra.Bemerkung":%'
   AND p.original NOT LIKE '%"extra.Fundbeschreibung":%'
   AND p.extra LIKE '%"Bemerkung"%'
   AND JSON_CONTAINS_PATH(p.extra, 'all', '$.Bemerkung', '$.Fundbeschreibung')
   AND NOT EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_lists l ON l.id = pl.list_id
                    JOIN map_list_fields f ON f.list_id = l.id
                    WHERE pl.point_id = p.id AND l.is_species = 0 AND f.name = 'Bemerkung')
   AND NOT EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_list_fields f ON f.list_id = pl.list_id
                    WHERE pl.point_id = p.id AND f.name = 'Fundbeschreibung');

-- A conflict about the field is a conflict about the description.
UPDATE map_point_overwritten o
  JOIN map_points p ON p.id = o.point_id
   SET o.field = 'extra.Fundbeschreibung'
 WHERE o.field = 'extra.Bemerkung'
   AND p.extra LIKE '%"Bemerkung"%'
   AND JSON_CONTAINS_PATH(p.extra, 'all', '$.Bemerkung', '$.Fundbeschreibung')
   AND NOT EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_lists l ON l.id = pl.list_id
                    JOIN map_list_fields f ON f.list_id = l.id
                    WHERE pl.point_id = p.id AND l.is_species = 0 AND f.name = 'Bemerkung')
   AND NOT EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_list_fields f ON f.list_id = pl.list_id
                    WHERE pl.point_id = p.id AND f.name = 'Fundbeschreibung');

-- 3. The old key goes.
UPDATE map_points p
   SET p.extra = JSON_REMOVE(p.extra, '$.Bemerkung')
 WHERE p.extra LIKE '%"Bemerkung"%'
   AND JSON_CONTAINS_PATH(p.extra, 'all', '$.Bemerkung', '$.Fundbeschreibung')
   AND NOT EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_lists l ON l.id = pl.list_id
                    JOIN map_list_fields f ON f.list_id = l.id
                    WHERE pl.point_id = p.id AND l.is_species = 0 AND f.name = 'Bemerkung')
   AND NOT EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_list_fields f ON f.list_id = pl.list_id
                    WHERE pl.point_id = p.id AND f.name = 'Fundbeschreibung');

-- 4. A copy the handing-on left in a list without species, still equal to
--    that list's own „Bemerkung", goes again.
UPDATE map_points p
   SET p.extra = JSON_REMOVE(p.extra, '$.Fundbeschreibung')
 WHERE p.extra LIKE '%"Fundbeschreibung"%'
   AND JSON_CONTAINS_PATH(p.extra, 'all', '$.Bemerkung', '$.Fundbeschreibung')
   AND JSON_EXTRACT(p.extra, '$.Bemerkung') = JSON_EXTRACT(p.extra, '$.Fundbeschreibung')
   AND EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_lists l ON l.id = pl.list_id
                JOIN map_list_fields f ON f.list_id = l.id
                WHERE pl.point_id = p.id AND l.is_species = 0 AND f.name = 'Bemerkung')
   AND NOT EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_lists l ON l.id = pl.list_id
                    WHERE pl.point_id = p.id AND l.is_species = 1)
   AND NOT EXISTS (SELECT 1 FROM map_point_lists pl JOIN map_list_fields f ON f.list_id = pl.list_id
                    WHERE pl.point_id = p.id AND f.name = 'Fundbeschreibung');

-- 5. A species list's own old „Bemerkung" — hidden, its values moved above.
DELETE f FROM map_list_fields f
  JOIN map_lists l ON l.id = f.list_id
 WHERE l.is_species = 1 AND f.name = 'Bemerkung';

-- 6. The share ticks of species lists: a list share's recipients, and the
--    old boxes' hung-in lists.
UPDATE map_container_members m
  JOIN map_containers c ON c.id = m.container_id
  JOIN map_lists l ON l.id = c.list_id
   SET m.rights = REPLACE(m.rights, '"field:Bemerkung"', '"field:Fundbeschreibung"')
 WHERE l.is_species = 1 AND m.rights LIKE '%"field:Bemerkung"%';

UPDATE map_container_lists cl
  JOIN map_lists l ON l.id = cl.list_id
   SET cl.rights = REPLACE(cl.rights, '"field:Bemerkung"', '"field:Fundbeschreibung"')
 WHERE l.is_species = 1 AND cl.rights LIKE '%"field:Bemerkung"%';
