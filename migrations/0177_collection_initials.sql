-- THE COLLECTOR'S INITIALS IN FRONT OF EVERY AUFSAMMLUNG'S NUMBER
-- (18.10.2026).
--
-- The box number A-YYYY-NN is counted per person and collecting year, so
-- Anna's „A-2026-01" and Ben's were the same number wherever one sees the
-- other's Aufsammlung. Since today collection_number_mint() writes the
-- first letter of the first name and of the last name in front —
-- „CW-A-2026-01" — and this migration gives the numbers already handed out
-- the same, so one person's run does not change its look in the middle.
--
-- The default TITLE goes along: an Aufsammlung without a name of its own is
-- called „Aufsammlung A-2026-01" (collection_after_create), and that title
-- would otherwise go on naming the old number. A title somebody wrote
-- himself is left alone. Titles first, while the old number still stands to
-- compare against. touched_at makes every device fetch the points afresh.
--
-- A person without first or last name keeps the bare number, as the minting
-- does (collection_initials).

UPDATE map_points p
  JOIN map_users u ON u.id = p.user_id
   SET p.title = CONCAT('Aufsammlung ', UPPER(LEFT(TRIM(u.first_name), 1)), UPPER(LEFT(TRIM(u.last_name), 1)), '-', p.collection_number)
 WHERE p.is_collection = 1
   AND p.collection_number LIKE 'A-%'
   AND p.title = CONCAT('Aufsammlung ', p.collection_number)
   AND TRIM(COALESCE(u.first_name, '')) <> ''
   AND TRIM(COALESCE(u.last_name, '')) <> '';

UPDATE map_points p
  JOIN map_users u ON u.id = p.user_id
   SET p.collection_number = CONCAT(UPPER(LEFT(TRIM(u.first_name), 1)), UPPER(LEFT(TRIM(u.last_name), 1)), '-', p.collection_number),
       p.touched_at = CURRENT_TIMESTAMP(6)
 WHERE p.is_collection = 1
   AND p.collection_number LIKE 'A-%'
   AND TRIM(COALESCE(u.first_name, '')) <> ''
   AND TRIM(COALESCE(u.last_name, '')) <> '';
