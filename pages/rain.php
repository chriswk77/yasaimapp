<?php
/**
 * The rain picture and its description, for the map's layer „Regen vor
 * 8–21 Tagen" (includes/rain.php makes both once a day).
 *
 *   rain.php?what=png    the picture, Web Mercator over RAIN_BOX
 *   rain.php?what=json   {from, to, days, made} — the window it shows
 *
 * NO LOGIN: it is the Deutscher Wetterdienst's open data, summed, and
 * nothing of anybody's in it — the map may lay it on before the start
 * data are there, an excursion guest sees it as well. A page and not an
 * API endpoint so the service worker keeps the last answer (pages are
 * network first, then the stored copy): the picture is there offline,
 * as of the last time it was seen.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/rain.php';

// Nothing here touches the session — let the next request of this visitor go.
session_write_close();

$meta = rain_meta();
if (($_GET['what'] ?? 'png') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    echo json_encode($meta ?? ['from' => null, 'to' => null, 'days' => 0, 'made' => null]);
    exit;
}

$file = RAIN_DIR . '/rain.png';
if (!is_file($file)) {
    http_response_code(404);
    exit;
}
// The address carries the day it was made (?v=), so the browser may keep it.
$tag = '"' . md5((string) ($meta['made'] ?? filemtime($file))) . '"';
header('ETag: ' . $tag);
header('Cache-Control: public, max-age=86400');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $tag) {
    http_response_code(304);
    exit;
}
header('Content-Type: image/png');
header('Content-Length: ' . filesize($file));
readfile($file);
