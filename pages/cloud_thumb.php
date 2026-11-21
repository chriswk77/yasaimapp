<?php
/**
 * Deliver the preview of a cloud recording — through this server.
 *
 *   cloud_thumb.php?id=12             the tile, 320 px
 *   cloud_thumb.php?id=12&large=1     for the viewer, up to 2048 px
 *   cloud_thumb.php?id=12&original=1  the recording itself, untouched
 *
 * THE ORIGINAL IS NOT A THIRD SIZE. 2048 wide by 1024 high is the provider's
 * ceiling for previews — beyond it there is only the file itself, and for a
 * stack or a micrograph that is exactly what one wants to look at. It is not
 * fetched unasked: several megabytes over a mobile connection are the user's
 * decision, so the window shows the preview at once and the original only
 * when asked.
 *
 * TWO SIZES AND NOT A FREE NUMBER. Every size is a request to the provider
 * and its own entry in every cache along the way; a width that comes out of
 * the address would multiply both for nothing. And the provider only takes
 * certain numbers anyway — width up to 2048, height up to 1024, both
 * divisible by four or five.
 *
 * WHY THIS DETOUR, and it is not one: PCLOUD'S PREVIEW LINKS ARE BOUND TO
 * THE IP THAT ASKED FOR THEM. Measured on 18.08.2026, and it is documented
 * nowhere — the provider answers a foreign address with the plain sentence
 * "This link was generated for another IP address."
 *
 * That is exactly what broke the preview on the live server while it worked
 * locally, and it looked like the strangest of bugs: the address arrived at
 * the browser, the picture behind it did not. Locally, server and browser
 * sit on the same machine and thus behind the same address, so everything
 * fitted. On the live server the link was made out to the SERVER — and the
 * only one who could not use it was the browser it was meant for.
 *
 * So whoever asks for the link has to be the one who fetches the picture.
 * The server does both and passes the bytes on.
 *
 * WHAT THIS GAINS BESIDES THE FIX:
 *   The recordings list no longer calls the provider at all. It used to
 *   fetch one link per file on every opening of a point — ten files, ten
 *   calls before anything was on screen. Now the picture is fetched when
 *   the browser asks for it, and only then.
 *   The address stays on our own origin, so the service worker can keep it
 *   like a voucher photo (see isPhoto() in sw.js) and it stands in the
 *   woods without reception.
 *   No token leaves the server for this.
 *
 * TOKENS DO NOT WORK HERE, same as photo.php: this route is meant for
 * <img src="…">, and an image request carries no headers of its own. It
 * stays with the session cookie.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cloud.php';

$me = login_required();
$userId = (int) $me['id'];

/* THE SESSION IS LET GO AT ONCE (10.10.2026). PHP keeps a session's file
   locked until the request ends, so every further request of the same
   visitor waits behind it — and this request waits on a cloud for up to
   seconds. The Fundbrowser asks for dozens of pictures at a time, and they
   came in one after the other, each behind the previous one's round trip
   to the provider („es lädt halt ziemlich langsam"). Nothing
   below writes to the session; tile_relay.php does the same. */
session_write_close();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0 || !cloud_ready($pdo) || !table_exists($pdo, 'map_cloud_files')) {
    http_response_code(404);
    exit;
}

// Not present and not mine are the same thing from the outside — anything
// else would say which ids are taken.
/* MINE, OR ON A FIND I MAY LOOK AT (11.09.2026, widened 14.09.2026).
   A picture on a co-owned find is shown to the whole circle; its bytes
   come from MY copy as soon as the worker has made one (my cloud, my
   token), and until then from the photographer's cloud with his token —
   the way his own preview is made. Since 14.09.2026 the same holds for
   whoever may merely SEE the find — a share member, the excursion's
   leader — through point_view_scope(), the one seeing gate: he has no
   copy and never gets one (the copies are the circle's), so his bytes
   always come the second way. A stranger still gets the 404 a missing
   id gets. */
$stmt = $pdo->prepare(
    'SELECT user_id, point_id, fileid, provider FROM map_cloud_files WHERE id = ? LIMIT 1'
);
$stmt->execute([$id]);
$row = $stmt->fetch();
if ($row === false) {
    http_response_code(404);
    exit;
}
/* EVERY RECORDING KNOWS ITS CLOUD (26.09.2026): the bytes are fetched
   where they lie — the provider on the row, or on MY copy's row — through
   the storage seam, whichever of the four it is. */
$source   = $userId;
$fileid   = (string) $row['fileid'];
$provider = (string) $row['provider'] ?: 'pcloud';
if ((int) $row['user_id'] !== $userId) {
    require_once __DIR__ . '/../includes/point_owners.php';
    if (point_view_scope($pdo, $userId, (int) $row['point_id']) === null) {
        http_response_code(404);
        exit;
    }
    $copy = false;
    if (table_exists($pdo, 'map_cloud_copies')) {
        $c = $pdo->prepare('SELECT fileid, provider FROM map_cloud_copies
                             WHERE origin_kind = "cloud" AND origin_id = ? AND user_id = ? AND status = "copied"');
        $c->execute([$id, $userId]);
        $copy = $c->fetch();
    }
    if ($copy !== false && (string) ($copy['fileid'] ?? '') !== '') {
        $fileid   = (string) $copy['fileid'];
        $provider = (string) ($copy['provider'] ?? '') ?: 'pcloud';
        $viaCopy  = true;
    } else {
        $source = (int) $row['user_id'];
    }
}

/* THE TILE COMES FROM THIS SERVER when it can (includes/thumbs.php,
   10.10.2026): the 320 px preview is kept here once fetched, and every
   later asking — any viewer, any device — is a file read instead of a
   round trip to the photographer's cloud. Made now on the first asking,
   or ahead of it by the hourly job; if no cloud hands one out, the live
   road below answers as before. */
$large = !empty($_GET['original']) || !empty($_GET['large']) || !empty($_GET['gross']);
require_once __DIR__ . '/../includes/thumbs.php';
if (!$large) {
    $kept = thumb_ensure($pdo, 'cloud', ['id' => $id] + $row);
    if ($kept !== null) {
        thumb_serve($kept);
        exit;
    }
}

$s = cloud_storage_for($pdo, $source, $provider);
/* MY COPY'S CLOUD IS GONE — disconnected, or a move closed with this copy
   left behind: the picture still lies in the photographer's cloud and
   comes from there, as it did before my copy existed. Until 26.09.2026
   „Trennen" made the others' pictures on shared finds go dark for me,
   although nothing of theirs had moved. */
if ($s === null && ($viaCopy ?? false)) {
    $source   = (int) $row['user_id'];
    $fileid   = (string) $row['fileid'];
    $provider = (string) $row['provider'] ?: 'pcloud';
    $s = cloud_storage_for($pdo, $source, $provider);
}
if ($s === null) {
    http_response_code(404);
    exit;
}

/* The preview at 320 for the tile, at 2048 for the viewer, or the file
   itself (getfilelink and not getthumblink at pCloud: the file, not a
   rendering of it). 'gross' is the name an offline-cached map.js may
   still ask with — without the fallback that device would get the 320 px
   tile in the viewer and it would merely look blurry, not broken. */
$edge = !empty($_GET['original']) ? null
      : (empty($_GET['large']) && empty($_GET['gross']) ? 320 : 2048);
try {
    // S3's previews hang off the app's prefix; nobody else needs it.
    $home = $s['provider'] === 's3' ? (string) cloud_home_resolve($pdo, $source, $s) : null;
    $candidates = storage_file_fetch($s, $fileid, $edge, $home);
} catch (RuntimeException $e) {
    error_log('cloud_thumb ' . $id . ': ' . $e->getMessage());
    $candidates = [];
}

if ($candidates === []) {
    // A raw file the provider cannot render is the ordinary case for a
    // preview. The reason is in the error log; the browser gets a plain 404
    // and the tile falls back to the file name.
    // The large picture not to be had means the picture may be gone in
    // the cloud — then its kept preview must not go on showing it.
    if ($large) {
        thumb_forget((int) $row['user_id'], 'cloud', $id);
    }
    http_response_code(404);
    exit;
}

/* THE VIEWER'S LARGE PREVIEW WITH THE ORIGINAL'S COLOUR PROFILE
   (16.11.2026: the picture in „Bearbeiten" warmer than its
   tile and its plate — includes/thumbs.php, THUMB_PROFILE_LOST, has the
   measurement). pCloud's 2048 preview carries the original's numbers
   without the profile that says how to read them; it is fetched here
   instead of streamed, and goes out with the profile put back. The
   profile is read once from the original's head and kept (thumb_icc).
   Where the original has none, or the preview is no JPEG, it streams as
   before.

   „c=1" ON EVERY PREVIEW ADDRESS (includes/finds.php, api/v1/cloud.php,
   photowindow.js, organize.js, report.php): the previews are sent as
   unchanging for a year, and the browser and the service worker kept the
   ones without the profile under the old address. The parameter itself
   means nothing here; it only makes the address a new one. */
if ($edge === 2048 && in_array((string) $s['provider'], THUMB_PROFILE_LOST, true)) {
    $icc = thumb_icc($pdo, 'cloud', ['id' => $id] + $row);
    if ($icc !== null) {
        $bytes = thumb_fetch($candidates);
        if ($bytes !== null && picture_type(substr($bytes, 0, 16)) === 'image/jpeg') {
            if (picture_icc($bytes, 'image/jpeg') === null) {
                $bytes = jpeg_with_icc($bytes, $icc);
            }
            picture_headers('image/jpeg', strlen($bytes), 'private, max-age=31536000, immutable');
            echo $bytes;
            exit;
        }
    }
}

// One place hands a picture on — see cloud_deliver() in includes/cloud.php.
// It was written twice for a moment, once here and once for the voucher
// photos, and the second copy was already drifting: the session headers
// were removed in one of them and not the other.
if (!cloud_deliver($candidates, 'cloud_thumb ' . $id)) {
    if ($large) {
        thumb_forget((int) $row['user_id'], 'cloud', $id);
    }
    http_response_code(502);
}
