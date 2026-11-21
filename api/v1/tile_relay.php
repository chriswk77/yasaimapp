<?php
/**
 * One official aerial picture, handed on for the vector map.
 *
 *   GET api/v1/tile_relay.php?relay=<region>&<the WMS GetMap query>
 *
 * For the states whose services let no web page read their pictures —
 * why, and what is let through: includes/tile_relay.php. Logged-in users
 * and excursion guests: a guest at an excursion in Schleswig-Holstein
 * looks at the same aerial image, and nothing private is in it.
 */

declare(strict_types=1);

const EXCURSION_GUESTS_ALLOWED = true;

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/tile_relay.php';
require_once __DIR__ . '/../../includes/picture_fence.php';

/* THE SESSION IS LET GO AT ONCE. PHP holds a session's file locked until
   the request ends, so every further request of the same visitor waits —
   and a screen of the map asks a dozen pictures at the same time. Nothing
   here writes to the session. */
session_write_close();

if ($method !== 'GET') {
    json_error(t('Nur lesen.'), 405);
}

$service = tile_relay_service((string) ($_GET['relay'] ?? ''));
if ($service === null) {
    json_error(t('Diesen Dienst gibt es hier nicht.'), 404);
}
$query = tile_relay_query($_GET, $service);
if ($query === null) {
    json_error(t('Welches Bild?'), 400);
}

$answer = tile_relay_fetch($service['url'] . (str_contains($service['url'], '?') ? '&' : '?') . $query);
// A state's server is not ours either: what is no picture by its bytes is not handed on (picture_fence.php).
if ($answer['status'] === 200 && picture_type($answer['body']) === null) {
    $answer['status'] = 502;
}
if ($answer['status'] !== 200) {
    http_response_code($answer['status']);
    header('Content-Type: text/plain; charset=utf-8');
    exit;
}

/* A picture of the ground, the same for everyone: the browser may keep it
   a week. The states renew their images every few years. */
picture_headers(picture_type($answer['body']), strlen($answer['body']), 'public, max-age=604800');
echo $answer['body'];
