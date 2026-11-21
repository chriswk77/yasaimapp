<?php
/**
 * The Fundbrowser's „Bildarchiv" (02.11.2026): the older
 * pictures of one's own and one's friends' picture archives, as a gallery
 * beside the finds (includes/picture_archives.php).
 *
 *   GET archive_finds.php                          every older find one may see — species,
 *                                                  day, place, its first picture and how many
 *   GET archive_finds.php?archive=<id>&key=<find key>&name=<species>
 *                                                  that find's pictures, in their order
 *
 * The pictures themselves always come through pages/old_picture.php, which
 * asks the rights again; no share link and no provider address ever
 * reaches the browser here. Read only — nothing of this becomes a point.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/picture_archives.php';

if ($method !== 'GET') {
    json_error(t('Gibt es nicht.'), 405);
}
// Before migration 0181 there is no archive — an empty gallery, not an error.
if (!archives_ready($pdo)) {
    json_response(['finds' => []]);
}

if (isset($_GET['archive'])) {
    $pictures = archive_find($pdo, (int) $me['id'], (int) $_GET['archive'],
        (string) ($_GET['key'] ?? ''), (string) ($_GET['name'] ?? ''));
    if ($pictures === null) {
        json_error(t('Gibt es nicht.'), 404);
    }
    json_response(['pictures' => $pictures]);
}

json_response(['finds' => archives_overview($pdo, (int) $me['id'])]);
