<?php
/**
 * One picture of a picture archive (includes/picture_archives.php).
 *
 *   old_picture.php?a=<archive>&f=<file id>&s=small|large|full
 *
 * WHY THE SERVER FETCHES AND HANDS ON: the
 * share link opens the whole folder to whoever holds it, so it never leaves
 * the server — and pCloud would not let a page read its files anyway (no
 * CORS, download addresses bound to whoever asked).
 *
 * WHO: the archive's owner and the friends he lets see it, and only a
 * file the reading placed under a species — the share holds .ods, raw
 * files and videos too, and an id is easily typed. A genus or species the
 * owner keeps back from a friend (archive_row_shown) is a 404 for him
 * here as well: the overview leaves it out, but an address can be typed.
 *
 * WHAT: a picture and nothing else, behind the fence every picture of
 * this server goes out with (includes/picture_fence.php) — the type by
 * the first bytes, never sniffed, in a sandbox. Somebody else's cloud is
 * not ours: a page saved as „.jpg" there must not run here. The large one
 * and the original are streamed through cloud_deliver(), never held in
 * memory: an original may be whatever a camera made.
 *
 * WHERE THE OWNER HIDES HIS FIND SPOTS from this friend (archive_hides_gps)
 * he gets every size without its GPS (picture_without_gps). The
 * original of a picture that is no JPEG cannot be cleaned here; that
 * friend gets the large preview in its place.
 *
 * THE SMALL PREVIEW IS KEPT on the server (data/picture_archives/<id>,
 * some 30 KB each) — a species with a hundred pictures would otherwise ask
 * pCloud a hundred times on every look. The large one and the original are
 * not: they are looked at one at a time, and a copy of somebody's picture
 * is what the server does not hold.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/picture_archives.php';

$me = user_current();
if ($me === null) {
    http_response_code(403);
    exit;
}
$userId = (int) $me['id'];
$archiveId = (int) ($_GET['a'] ?? 0);
$fileId = (string) ($_GET['f'] ?? '');
$size = (string) ($_GET['s'] ?? 'small');
if (!preg_match('~^[A-Za-z0-9_-]{1,64}$~', $fileId) || !in_array($size, ['small', 'large', 'full'], true)) {
    http_response_code(400);
    exit;
}

if ($archiveId <= 0 || !archives_ready($pdo)) {
    http_response_code(404);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT a.*, p.name FROM map_picture_archives a
                             JOIN map_archive_pictures p ON p.archive_id = a.id AND p.file_id = ?
                            WHERE a.id = ?');
    $stmt->execute([$fileId, $archiveId]);
    $archive = $stmt->fetch(PDO::FETCH_ASSOC);
    $sight = archive_sight($pdo, $userId);
    if ($archive === false || archive_row_shown($archive, $userId, $sight) === null) {
        http_response_code(404);
        exit;
    }
    // Nothing below writes to the session; let go of it, or a page of previews comes one after the other.
    session_write_close();
    $link = archive_link($archive) ?? throw new RuntimeException('link unreadable');
    $withoutGps = archive_hides_gps($archive, $userId, $sight);
    if ($size === 'small') {
        $path = archive_small($archive, $link, $fileId);
    } else {
        /* A month in the browser, privately. Not „immutable": the folder may
           be a backup of somebody's disk, and a picture overwritten there
           may keep its id. */
        $note = 'archive ' . $archiveId . '/' . $fileId;
        $addresses = archive_addresses($link, $fileId, $size);
        if ($addresses === [] || !cloud_deliver($addresses, $note, 'private, max-age=2592000', $withoutGps)) {
            if (!$withoutGps || $size !== 'full'
                || !cloud_deliver(archive_addresses($link, $fileId, 'large'), $note, 'private, max-age=2592000', true)) {
                http_response_code(502);
            }
        }
        exit;
    }
} catch (RuntimeException $e) {
    // A failure, not a 404 — the picture is there, the cloud was only slow; the browser may ask again.
    error_log('old_picture: ' . $e->getMessage());
    http_response_code(502);
    exit;
}

$bytes = (string) file_get_contents($path);
if ($withoutGps) {
    $bytes = picture_without_gps($bytes);
}
picture_headers(picture_type($bytes), strlen($bytes), 'private, max-age=2592000');
echo $bytes;
