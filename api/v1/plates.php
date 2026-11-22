<?php
/**
 * Plates of a find (migration 0155) — see includes/plates.php.
 *
 *   GET  plates.php?point=<find>                        the plates one may see
 *   POST plates.php {point_id, id?, since?, layout}     save (new without id)
 *   POST plates.php {action: delete, id}                the maker takes it down
 *   POST plates.php multipart {action: image, id, page, hash, image}
 *                                                       one drawn page into the
 *                                                       maker's cloud (plate_images.php)
 *
 * Online only, like the editor that sends it: a plate is made at the desk
 * with the pictures loaded, never in the woods — the queue has nothing to
 * hold here.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/plates.php';

if ($me === null) {
    json_error(t('Dafür musst du angemeldet sein.'), 403);
}
$userId = (int) $me['id'];

if ($method === 'GET') {
    $pointId = (int) ($_GET['point'] ?? 0);
    require_once __DIR__ . '/../../includes/plate_images.php';
    json_response([
        'plates' => $pointId > 0 ? plates_for_point($pdo, $userId, $pointId) : [],
        'ready'  => plates_ready($pdo),
        // Whether a drawn page has somewhere to go: the viewer's cloud for pictures,
        // and the table that remembers it (migration 0169).
        'cloud'  => plate_images_ready($pdo) && cloud_pictures_ready($pdo, $userId),
    ]);
}
if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
if (!plates_ready($pdo)) {
    json_error(t('Tafeln brauchen die Migration 0155 — sie ist noch nicht eingespielt.'), 503);
}

/* ONE DRAWN PAGE INTO THE MAKER'S CLOUD (13.10.2026): the bytes as a
   file field — a 200 dpi page is a megabyte, and JSON would carry it as
   a third more. */
if (($input['action'] ?? '') === 'image') {
    require_once __DIR__ . '/../../includes/plate_images.php';
    if (!plate_images_ready($pdo)) {
        json_response(['stored' => false]);
    }
    $file = $_FILES['image'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        json_error(t('Die Seite kam nicht als Bild an.'), 422);
    }
    /* THE SESSION LETS GO BEFORE THE CLOUD WORK (22.11.2026). PHP holds
       a session locked for the whole request, and handing a page to the
       cloud takes seconds. Every other request of the same login waited
       behind it — the editor's next save among them, until its twelve
       seconds ran out (api.js, TIMEOUT). The browser then called the save
       failed while the server, its turn come, stored it all the same, and
       from then on every save was refused as one from „another device"
       (the editor sent the updated_at from before). Nothing below writes
       to the session; reading it stays possible. */
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    try {
        $answer = plate_image_store($pdo, $userId, (int) ($input['id'] ?? 0), (int) ($input['page'] ?? 0),
            (string) ($input['hash'] ?? ''), (string) file_get_contents($file['tmp_name']));
    } catch (InvalidArgumentException $e) {
        json_error($e->getMessage(), 422);
    } catch (RuntimeException $e) {
        error_log('plate image: ' . $e->getMessage());
        json_error(t('Die Cloud hat die Seite gerade nicht angenommen.'), 502);
    }
    json_response($answer);
}

if (($input['action'] ?? '') === 'delete') {
    $id = (int) ($input['id'] ?? 0);
    if (!plate_delete($pdo, $userId, $id)) {
        json_error(t('Diese Tafel ist nicht deine — oder es gibt sie nicht mehr.'), 404);
    }
    json_response(['deleted' => $id]);
}

$pointId = (int) ($input['point_id'] ?? 0);
$id      = (int) ($input['id'] ?? 0);
if ($pointId <= 0) {
    json_error(t('Kein Punkt angegeben.'));
}
try {
    $since = is_string($input['since'] ?? null) ? $input['since'] : null;
    $plate = plate_save($pdo, $userId, $pointId, $id > 0 ? $id : null, $input['layout'] ?? null, $since);
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 422);
}
json_response(['plate' => $plate], $id > 0 ? 200 : 201);
