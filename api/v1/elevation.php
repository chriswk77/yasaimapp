<?php
/**
 * Look up a point's terrain elevation from the DGM1.
 *
 *   POST elevation.php?id=5
 *
 * Answers with the updated point.
 *
 * Deliberately its own endpoint and not a field in points.php: here a
 * coordinate leaves the server and goes to a third party. That should be
 * visible in the log and not dragged along in an update call that otherwise
 * only concerns title and note.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/elevation.php';

$userId = (int) $me['id'];

if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}

$id = request_id();
if ($id <= 0) {
    json_error(t('Keine Kennung angegeben.'));
}

$point = point_load($pdo, $userId, $id);
if ($point === null) {
    json_error(t('Punkt nicht gefunden.'), 404);
}

try {
    $result = elevation_get((float) $point['lat'], (float) $point['lon']);
} catch (RuntimeException $e) {
    // 502: it is not us who did something wrong, but the service behind
    // could not deliver.
    json_error($e->getMessage(), 502);
}

$pdo->prepare(
    'UPDATE map_points
        SET elevation_m = ?, elevation_source = ?, elevation_accuracy_m = NULL, updated_at = NOW()
      WHERE id = ? AND user_id = ?'
)->execute([(int) round($result['height']), 'dgm1', $id, $userId]);

json_response([
    'point'    => point_load($pdo, $userId, $id),
    'as_of'    => $result['as_of'],
    'attribution' => $result['attribution'],
]);
