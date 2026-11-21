<?php
/**
 * The photographer's scale presets, for a picture one may only look at.
 *
 *   GET scales.php?point=<find>&user=<photographer>
 *
 * 06.10.2026, measuring on a find Finn shares with him to read:
 * „Bei den Maßstäben, die ich sehe, sind das meine oder Finns? … Sinn
 * machen würde es natürlich, Finns zu sehen. Weil er ja mit seinem
 * Mikroskop aufgenommen hat. Und dann seine entsprechenden Eichbilder und
 * Maßstäbe angegeben hat, die auf das Bildformat passen." The chooser
 * offered the VIEWER's presets — his own objectives, another camera's
 * pixels. On a foreign picture it offers the photographer's now, to try
 * one out in the window (photowindow.js keeps that there).
 *
 * WHO MAY ASK: whoever may see the find (point_view_scope), and only for a
 * photographer with a picture on it — else any find one sees would open
 * anybody's presets. What goes out is what a scale needs: name, µm per
 * pixel and the calibration image's size; where that image lies in his
 * cloud stays his. The device each objective belongs to goes along (0183).
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/photos.php';
require_once __DIR__ . '/../../includes/point_owners.php';

if ($method !== 'GET') {
    json_error(t('Methode nicht unterstützt.'), 405);
}

$pointId = (int) ($_GET['point'] ?? 0);
$owner   = (int) ($_GET['user'] ?? 0);
if ($pointId <= 0 || $owner <= 0 || point_view_scope($pdo, (int) $me['id'], $pointId) === null) {
    json_error(t('Punkt nicht gefunden.'), 404);
}

$sql = 'SELECT 1 FROM map_photos WHERE point_id = ? AND user_id = ?';
$args = [$pointId, $owner];
if (table_exists($pdo, 'map_cloud_files')) {
    $sql .= ' UNION SELECT 1 FROM map_cloud_files WHERE point_id = ? AND user_id = ?';
    array_push($args, $pointId, $owner);
}
$q = $pdo->prepare($sql . ' LIMIT 1');
$q->execute($args);
if ($q->fetchColumn() === false) {
    json_response(['scales' => []]);
}

json_response(['scales' => array_map(static fn(array $s): array => [
    'id'        => (int) $s['id'],
    'name'      => (string) $s['name'],
    'um_per_px' => (float) $s['um_per_px'],
    'width'     => isset($s['width']) && $s['width'] !== null ? (int) $s['width'] : null,
    'height'    => isset($s['height']) && $s['height'] !== null ? (int) $s['height'] : null,
    // His devices too (0183), so the viewer offers his travel microscope's
    // objectives apart from his lab microscope's.
    'device_id' => $s['device_id'],
    'device'    => $s['device'],
], photo_scales($pdo, $owner))]);
