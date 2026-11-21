-- „Makrochemie Sporenpulver" is called „Makrochemie SPP" (15.11.2026).
--
-- „Makrochemie. Sporenpulver. Können wir abkürzen in
-- Makrochemie SPP … jedenfalls in Dateinamen. Also da muss nicht
-- Makrochemie, Sporenpulver stehen, sondern Makrochemie, SPP." The
-- subject name is the word in the file name, so the stock name itself
-- became the short form (includes/photos.php, PHOTO_TITLES_MACRO; saved
-- lists carry the old word over on reading, PHOTO_TITLES_RENAMED).
--
-- WHAT THIS DOES: every picture that carries the old word gets the new
-- one — voucher photos and cloud recordings alike — and its find is put
-- on the names worker's list (map_cloud_names_due, as 0196 does), so the
-- five-minute bundle renames the files in the cloud. Nothing else
-- changes. Before it runs, such pictures simply keep the old word.
INSERT INTO map_cloud_names_due (point_id, due_at)
    SELECT DISTINCT point_id, NOW(6) FROM map_photos
     WHERE title = 'Makrochemie Sporenpulver' AND point_id IS NOT NULL
    ON DUPLICATE KEY UPDATE due_at = NOW(6);
INSERT INTO map_cloud_names_due (point_id, due_at)
    SELECT DISTINCT point_id, NOW(6) FROM map_cloud_files
     WHERE title = 'Makrochemie Sporenpulver' AND point_id IS NOT NULL
    ON DUPLICATE KEY UPDATE due_at = NOW(6);
UPDATE map_photos SET title = 'Makrochemie SPP' WHERE title = 'Makrochemie Sporenpulver';
UPDATE map_cloud_files SET title = 'Makrochemie SPP' WHERE title = 'Makrochemie Sporenpulver';
