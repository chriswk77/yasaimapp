<?php
/**
 * Finds shown to a friend — „Direkt teilen" (migration 0149,
 * includes/point_hints.php says what a hint is).
 *
 *   POST hints.php {point_id, friend_id, on: true}    show my find to him
 *   POST hints.php {point_id, friend_id, on: false}   stop showing it to him
 *   POST hints.php {point_id, action: leave}          the friend's door: the
 *                                                     hint goes, for him alone
 *
 * ONE FRIEND PER CALL — a tick is one name, and „Ben out, Carla stays" is
 * then simply what happens. The answer carries the friends it is shown to
 * afterwards, so the tile draws exactly what is stored.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/point_hints.php';

if ($me === null) {
    json_error(t('Dafür musst du angemeldet sein.'), 403);
}
$userId = (int) $me['id'];

if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
if (!point_hints_ready($pdo)) {
    json_error(t('Direkt teilen braucht die Migration 0149 — sie ist noch nicht eingespielt.'), 503);
}

$pointId = (int) ($input['point_id'] ?? 0);
if ($pointId <= 0) {
    json_error(t('Kein Punkt angegeben.'));
}

if (($input['action'] ?? '') === 'leave') {
    if (!point_hint_leave($pdo, $userId, $pointId)) {
        json_error(t('Diesen direkt geteilten Punkt gibt es nicht mehr.'), 404);
    }
    json_response(['left' => $pointId]);
}

$friendId = (int) ($input['friend_id'] ?? 0);
if ($friendId <= 0) {
    json_error(t('Kein Freund angegeben.'));
}
try {
    $recipients = point_hint_set($pdo, $userId, $pointId, $friendId, !empty($input['on']));
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 422);
}
json_response(['point_id' => $pointId, 'recipients' => $recipients,
               'others' => point_hint_recipients($pdo, $userId, $pointId)['others']]);
