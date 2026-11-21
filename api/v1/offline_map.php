<?php
/**
 * One piece of an offline map file from pCloud — for area downloads.
 *
 *   GET api/v1/offline_map.php?file=europe&offset=<byte>&length=<bytes>
 *
 * Answers exactly those bytes, as application/octet-stream. Why the server
 * reads them and not the phone: includes/offline_map.php. Logged-in users
 * only, the files named in config.php only, and pieces of at most
 * OFFLINE_MAP_PIECE_MAX — a relay for tiles, not for files.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/offline_map.php';

if ($method !== 'GET') {
    json_error(t('Nur lesen.'), 405);
}

$file   = (string) ($_GET['file'] ?? '');
$offset = filter_var($_GET['offset'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$length = filter_var($_GET['length'] ?? null, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => OFFLINE_MAP_PIECE_MAX]]);
if (!preg_match('/^[a-z0-9_-]{1,40}$/', $file) || $offset === false || $length === false) {
    json_error(t('Welches Stück welcher Datei?'), 400);
}

try {
    $bytes = offline_map_piece($file, $offset, $length);
} catch (DomainException $e) {
    json_error(t('Diese Offline-Datei gibt es hier nicht.'), 404);
} catch (RuntimeException $e) {
    error_log('offline_map ' . $file . ': ' . $e->getMessage());
    json_error(t('Die Offline-Datei ist gerade nicht erreichbar.'), 502);
}

header('Content-Type: application/octet-stream');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
