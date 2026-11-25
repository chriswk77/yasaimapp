<?php
/**
 * Aufsammlungen (migration 0174, includes/collections.php).
 *
 *   GET  collections.php?id=3            the species that grew from Aufsammlung 3
 *   POST collections.php?id=3&what=find  turn Aufsammlung 3 into a find
 *                                        (collection_to_find) — answers the point
 *
 * Creating and saving go through points.php like every point — an
 * Aufsammlung is one (is_collection), a species one with collection_id.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';

$userId = (int) $me['id'];

if ($method === 'POST' && ($_GET['what'] ?? '') === 'find') {
    try {
        collection_to_find($pdo, $userId, request_id());
    } catch (InvalidArgumentException $e) {
        json_error(error_for_user($e), 422);
    }
    json_response(point_load($pdo, $userId, request_id()));
}
if ($method !== 'GET') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
$species = collection_species($pdo, $userId, request_id());
if ($species === null) {
    json_error(t('Diese Aufsammlung gibt es nicht.'), 404);
}
json_response(['species' => $species]);
