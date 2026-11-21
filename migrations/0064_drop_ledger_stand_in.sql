-- The German ledger has served out. Since 0063 the runner records into
-- map_migrations, which it creates itself and into which it copies the
-- old rows on first contact (migrations.php). By the time this file runs,
-- that has long happened — this file is applied by the new runner.
DROP TABLE IF EXISTS map_migrationen;
