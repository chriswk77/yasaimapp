<?php
/**
 * The Fundbrowser's stock (includes/finds.php says what and why).
 *
 *   GET finds.php            every find the caller may see, with its pictures, and
 *                            how many older finds his picture archives hold (archive)
 *   GET finds.php?id=<point> one find with all the cockpit asks of it
 *   GET finds.php?cover=<point> the address of its first picture's preview
 *                            (the desk map's name tag), or null
 *
 * READ ONLY — no CSRF, _start.php asks for it on writing methods alone.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/finds.php';

if ($method !== 'GET') {
    json_error(t('Methode nicht unterstützt.'), 405);
}

$cover = (int) ($_GET['cover'] ?? 0);
if ($cover > 0) {
    json_response(['thumb' => find_cover($pdo, (int) $me['id'], $cover)]);
}

$id = request_id();
if ($id > 0) {
    $find = find_cockpit($pdo, (int) $me['id'], $id);
    $find === null ? json_error(t('Punkt nicht gefunden.'), 404) : json_response($find);
}

/* THE BILDARCHIV'S COUNT rides along (02.11.2026): the page offers the
   switch only where there is something behind it, and the archive itself
   is fetched when one turns to it (api/v1/archive_finds.php). */
require_once __DIR__ . '/../../includes/picture_archives.php';
json_response([
    'finds' => finds_publish_marks($pdo, $me, finds_overview($pdo, (int) $me['id'])),
    'archive' => archives_ready($pdo) ? archives_count($pdo, (int) $me['id']) : 0,
]);
