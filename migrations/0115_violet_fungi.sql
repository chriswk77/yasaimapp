-- The standard colour of the „Pilze" list is violet (23.09.2026:
-- „das passt gut zu unserem Standardthema"). account_create() hands it to
-- every new account; this brings along the lists migration 0113 gave the
-- standard brown the same morning — the test accounts of that day.
-- Only lists that still carry EXACTLY that standard (name,
-- brown, mushroom, directly under a root „Meine Funde") change: a colour
-- somebody picked himself — Finn's red — stays his.
UPDATE map_lists l
  JOIN map_lists p ON p.id = l.parent_id
   SET l.color = '#8e6bb5', l.updated_at = NOW()
 WHERE l.name = 'Pilze' AND l.color = '#6b5b4a' AND l.symbol = 'mushroom'
   AND l.deleted_at IS NULL
   AND p.name = 'Meine Funde' AND p.parent_id IS NULL AND p.deleted_at IS NULL;
