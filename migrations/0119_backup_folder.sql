-- The backup folder gets a name of the user's own, and is known by its id
-- (23.09.2026: „Datensicherung könnten ja manche schon
-- reserviert haben … dass wir vorschlagen YASAIMAPP, aber jeder kann
-- diesen Ordnernamen ändern").
--
--   backup_folder     the name, as the user chose it or as the sweep last
--                     found it; NULL means the default, „YASAIMAPP".
--   backup_folder_id  the provider's id of that folder — a number at
--                     pCloud, a string at Drive and OneDrive, the prefix
--                     at S3. With it the sweep finds the folder again
--                     after the user renamed or moved it by hand, instead
--                     of creating a second one beside it.
--
-- ON THE CONNECTION, like backup_at (see 0085): disconnecting takes the
-- whole arrangement with it, and reconnecting starts clean.
--
-- WHOEVER ALREADY HAS A BACKUP KEEPS „Datensicherung": the folder exists
-- in their storage with every month in it, and nothing moves unasked. A
-- sweep that ran (backup_at) or a month on record is the sign. They can
-- rename it in the Cloud tab; the folder is then renamed where it lies,
-- nothing is uploaded again.
ALTER TABLE map_cloud_accounts
    ADD COLUMN backup_folder    VARCHAR(100) NULL AFTER backup_at,
    ADD COLUMN backup_folder_id VARCHAR(190) NULL AFTER backup_folder;

UPDATE map_cloud_accounts
   SET backup_folder = 'Datensicherung'
 WHERE backup_at IS NOT NULL
    OR user_id IN (SELECT user_id FROM map_cloud_backups);
