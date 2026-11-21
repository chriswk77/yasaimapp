<?php
/**
 * Co-ownership, the client's side of it (migration 0098).
 *
 *   POST owners.php {point_id, list_id|null}      file a co-owned find in
 *                                                 one of MY lists — „jeder
 *                                                 mit seiner eigenen Ablage"
 *   POST owners.php {point_id, action: leave}     the door: out, into my trash
 *   POST owners.php {point_id, action: restore}   back in as a co-owner
 *   POST owners.php {point_id, action: forget}    out for good, my copies go
 *
 * One list, not many: a co-owner's filing is a single row, and one place
 * is what „Ablage" means. The list has to be his own.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/point_owners.php';

if ($me === null) {
    json_error(t('Dafür musst du angemeldet sein.'), 403);
}
$userId = (int) $me['id'];

if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
if (!point_owners_ready($pdo)) {
    json_error(t('Der Mitbesitz braucht die Migration 0098 — sie ist noch nicht eingespielt.'), 503);
}

$pointId = (int) ($input['point_id'] ?? 0);
if ($pointId <= 0) {
    json_error(t('Kein Punkt angegeben.'));
}
$action = (string) ($input['action'] ?? 'list');
switch ($action) {
    // The door: out into my trash, the find stays with the others.
    case 'leave':
        if (!point_owner_leave($pdo, $pointId, $userId)) {
            json_error(t('Punkt nicht gefunden.'), 404);
        }
        json_response(['left' => $pointId]);
    case 'restore':
        if (!point_owner_restore($pdo, $pointId, $userId)) {
            json_error(t('Punkt liegt nicht im Papierkorb.'), 404);
        }
        json_response(['restored' => $pointId]);
    case 'forget':
        if (!point_owner_forget($pdo, $pointId, $userId)) {
            json_error(t('Punkt liegt nicht im Papierkorb.'), 404);
        }
        json_response(['forgotten' => $pointId]);
    case 'list':
        $listId = isset($input['list_id']) && $input['list_id'] !== null && $input['list_id'] !== ''
            ? (int) $input['list_id'] : null;
        if (!point_owner_list_set($pdo, $pointId, $userId, $listId)) {
            json_error(t('Punkt nicht gefunden.'), 404);
        }
        json_response(['lists' => $listId === null ? [] : [$listId]]);
    default:
        json_error(t('Unbekannte Aktion.'));
}
