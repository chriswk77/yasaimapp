-- The app's own folder where the provider gives none
-- (23.09.2026, the same evening as 0119).
--
-- 0119 gave the BACKUP folder a name of the user's own, defaulting to
-- „YASAIMAPP", because at Google Drive it lay loose in „Meine Ablage".
-- The picture was the better order: pCloud (a Private App) and
-- OneDrive hand the app a folder of its own, and everything the app
-- writes lives inside it; Google Drive and S3 give none — so the app
-- makes one, „YASAIMAPP", and the backup folder is „Datensicherung"
-- inside it, at every provider alike. Inside the app's own folder it can
-- collide with nothing, and the name the user may change is the one that
-- stands in the root.
--
-- So the two columns of 0119 change their meaning — and therefore their
-- name, since one name runs from column to property:
--
--   home_folder     the name of the app's own folder, only where the app
--                   makes it (Drive, S3); NULL means „YASAIMAPP".
--   home_folder_id  its id at Drive, its prefix at S3.
--
-- THE VALUES GO: they named backup folders. 0119 wrote „Datensicherung"
-- into every connection that had backed up — at pCloud, which keeps
-- exactly that name now anyway. Google Drive was not yet set up on the
-- live server, so no Drive folder is orphaned; a sweep at S3 since 0119
-- would find its months under the new prefix and write them once more.
ALTER TABLE map_cloud_accounts
    CHANGE COLUMN backup_folder    home_folder    VARCHAR(100) NULL,
    CHANGE COLUMN backup_folder_id home_folder_id VARCHAR(190) NULL;

UPDATE map_cloud_accounts SET home_folder = NULL, home_folder_id = NULL;
