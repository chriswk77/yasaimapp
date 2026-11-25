<?php
/**
 * Offline areas — outline and measurements.
 *
 *   GET    areas.php        all areas
 *   POST   areas.php        create (name and corners)
 *   PATCH  areas.php?id=3   rename or store measurements
 *   DELETE areas.php?id=3   delete
 *
 * The tiles sit in the browser of the respective device and never go over
 * the wire here; likewise zoom level and expiry, for both are decided by the
 * device that loads. An area is usually drawn at the desk, loaded on the
 * phone — which is why the outline must be shared and the storage must not.
 *
 * The measurements, by contrast, belong to everyone again: what a tile of
 * this layer weighs at this spot depends on the terrain, not the device.
 * Measured once, it spares every further device a dozen requests to third-
 * party services.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';

$userId = (int) $me['id'];

switch ($method) {
    case 'GET':
        // No cleanup anymore: an outline does not expire. What expires are
        // the tiles on a device, and that device clears those itself.
        json_response(['areas' => areas_load($pdo, $userId)]);

    case 'POST':
        try {
            $id = area_create($pdo, $userId, $input);
        } catch (InvalidArgumentException $e) {
            json_error(error_for_user($e));
        }
        $all = areas_load($pdo, $userId);
        $fresh = array_values(array_filter($all, static fn(array $g) => $g['id'] === $id));
        json_response($fresh[0] ?? ['id' => $id], 201);

    case 'PATCH':
    case 'PUT':
        $id = request_id();
        if ($id <= 0) {
            json_error(t('Keine Kennung angegeben.'));
        }
        try {
            $ok = area_update($pdo, $userId, $id, $input);
        } catch (InvalidArgumentException $e) {
            json_error(error_for_user($e));
        }
        if (!$ok) {
            json_error(t('Gebiet nicht gefunden oder nichts zu ändern.'), 404);
        }
        json_response(['areas' => areas_load($pdo, $userId)]);

    case 'DELETE':
        $id = request_id();
        if (!area_delete($pdo, $userId, $id)) {
            json_error(t('Gebiet nicht gefunden.'), 404);
        }
        json_response(['deleted' => $id, 'areas' => areas_load($pdo, $userId)]);

    default:
        json_error(t('Methode nicht unterstützt.'), 405);
}
