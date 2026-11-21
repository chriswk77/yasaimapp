<?php
/**
 * Deliver a voucher photo.
 *
 *   photo.php?id=12       the image
 *   photo.php?id=12&k=1   the preview image
 *
 * WHY VIA PHP AND NOT DIRECTLY. The images live under data/, that is behind
 * a "Deny from all" — the web server does not hand them out. That is not a
 * complication but the point: a find photo is as private as the coordinate
 * where it was taken. Whoever guesses the ID should still get nothing, and
 * without a place that first asks who the image belongs to, that would not
 * work.
 *
 * TOKENS DO NOT WORK HERE. This route is meant for <img src="…">, and an
 * image request carries no headers of its own. It stays with the session
 * cookie — for the PWA that is the same thing, it runs in the same origin.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/photos.php';

/* TWO KINDS OF PEOPLE, like on the map itself (stage 6a): the logged-in
   user as ever, or an excursion guest whose session the join page set.
   The guest gets his OWN pictures delivered — the ownership question
   below runs through excursion_guest_point, the same border as in the
   points API. Nobody else gets past this line. */
$me = user_current();
$actor = null;
if ($me === null) {
    require_once __DIR__ . '/../includes/excursions.php';
    $actor = excursion_actor($pdo);
    if ($actor === null) {
        http_response_code(401);
        exit;
    }
}

/* THE SESSION IS LET GO AT ONCE (10.10.2026). PHP keeps a session's file
   locked until the request ends, so every further request of the same
   visitor waits behind it — and this request waits on a cloud for up to
   seconds. The Fundbrowser asks for dozens of pictures at a time, and they
   came in one after the other, each behind the previous one's round trip
   to the provider („es lädt halt ziemlich langsam"). Nothing
   below writes to the session; tile_relay.php does the same. */
session_write_close();

$id = (int) ($_GET['id'] ?? 0);
$photo = $me !== null && $id > 0 ? photo_load($pdo, (int) $me['id'], $id) : null;

if ($actor !== null && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM map_photos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row !== false
        && excursion_guest_point($pdo, $actor, (int) $row['point_id']) !== null) {
        $photo = $row;
    }
}

/* WHOSE DIRECTORY THE FILE LIES IN. For one's own photo that is oneself;
   for a shared or guest one it is its owner — the bytes live under
   data/users/<owner>/photos/, and the cloud tokens belong to the owner
   too. Everything below therefore asks $ownerId, not the session. */
$ownerId = $photo === null ? 0 : (int) $photo['user_id'];

/* SHARING A LIST SHARES ITS PHOTOS (23.08.2026: "mit den
   Punkten gibt man seine Fotos auch frei" — in the whole interface, not
   only in the report). Until then this route was owner-bound, so a shared
   find showed its picture strip and every single one of them came back
   404. The right is not invented here: whoever may SEE the find may see
   what it looks like — and since 14.09.2026 that question has one
   answer for every place that hands out pictures, point_view_scope():
   the share, the excursion's leader and participant (stages 6 and 7),
   and the circle of a co-owned find, which the three checks that stood
   here spelled out one by one had left out. Logged-in people only — a
   guest's whole world was answered above. */
if ($me !== null && $photo === null && $id > 0) {
    require_once __DIR__ . '/../includes/point_owners.php';
    $stmt = $pdo->prepare('SELECT * FROM map_photos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row !== false
        && point_view_scope($pdo, (int) $me['id'], (int) $row['point_id']) !== null) {
        $photo   = $row;
        $ownerId = (int) $row['user_id'];
    }
}

// Not present and not visible to me are the same thing from the outside.
// Anything else would reveal which IDs are taken.
if ($photo === null) {
    http_response_code(404);
    exit;
}

$small = !empty($_GET['k']);

/* THE PICTURE MAY HAVE MOVED ON. Since migration 0056 a voucher photo can
   lie in the user's own storage — the row stays here, only the bytes went
   (see includes/photo_cloud.php). Then this is fetched from there and
   handed on, exactly as pages/cloud_thumb.php does it for the recordings,
   and for the same reason: the provider makes its links out to the address
   that asked for them, so the browser cannot use them.

   Neither the address nor the answer changes for whoever asked. That is
   the point of doing it here: nothing in the application needs to know
   where a picture happens to lie. */
if ((string) ($photo['cloud_fileid'] ?? '') !== '') {
    /* THE PREVIEW FROM THIS SERVER when it can (includes/thumbs.php,
       10.10.2026) — kept once fetched, a file read every time after. The
       large picture still comes from the cloud; if it does not, the
       picture may be gone there, and its kept preview goes too. */
    require_once __DIR__ . '/../includes/thumbs.php';
    if ($small) {
        $kept = thumb_ensure($pdo, 'photo', $photo);
        if ($kept !== null) {
            thumb_serve($kept);
            exit;
        }
    }
    require_once __DIR__ . '/../includes/photo_cloud.php';
    photo_from_cloud($pdo, $ownerId, $photo, $small);
    if (!$small && http_response_code() >= 400) {
        thumb_forget($ownerId, 'photo', (int) $photo['id']);
    }
    exit;
}

$path = photo_file($ownerId, (string) $photo['filename'], $small);

// If the preview image is missing, the large one will do as well. A broken
// image in the view would be the worse answer to an upload where only the
// second file did not make it through.
if ($small && ($path === null || !is_file($path))) {
    $path = photo_file($ownerId, (string) $photo['filename'], false);
}

if ($path === null || !is_file($path)) {
    http_response_code(404);
    exit;
}

// AN ID POINTS AT THE SAME BYTES FOREVER. The file name is random and is
// never reused; a changed image would be a new photo with a new ID. That makes
// "immutable" not optimistic but true — and the phone never fetches the same
// shot a second time over the mobile connection.
//
// 'private' is no mere accessory here: without it, a cache along the way would
// be allowed to keep the image and show it to the next person who requests
// the same address. For shots that belong to private find spots, that would be
// exactly the kind of mistake you only notice once it is too late.
//
// The upload was held to JPEG (photo_image_check); the bytes still say what
// goes out, as everywhere (includes/picture_fence.php).
require_once __DIR__ . '/../includes/picture_fence.php';
picture_headers(picture_type((string) file_get_contents($path, false, null, 0, 16)), (int) filesize($path),
    'private, max-age=31536000, immutable', 'foto-' . $id . ($small ? '-klein' : '') . '.jpg');
readfile($path);
