<?php
/**
 * The identification walks at a find (includes/point_keyings.php says what
 * and why; the identification module is the other side of the contract).
 *
 *   GET  keyings.php?point=5                       every walk at the find — mine, and the others'
 *   POST keyings.php {point, walk}                 store my walk
 *   POST keyings.php {point, walk, confirmed}      … with the species confirmed on it
 *   POST keyings.php {point, way}                  store the way I typed (empty takes it away)
 *
 * The species itself goes into the find through PATCH points.php — this
 * endpoint keeps the way, not the name of the find.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/point_keyings.php';

if ($method === 'GET') {
    $pointId = (int) ($_GET['point'] ?? 0);
    $answer = $pointId > 0 ? point_keyings_for($pdo, (int) $me['id'], $pointId) : null;
    $answer === null ? json_error(t('Punkt nicht gefunden.'), 404) : json_response($answer);
}

if ($method === 'POST') {
    $pointId = (int) ($input['point'] ?? 0);
    // The typed way (migration 0198): a string, and nothing of the walk is touched.
    if ($pointId > 0 && array_key_exists('way', $input) && is_string($input['way']) && !array_key_exists('walk', $input)) {
        if (!point_keying_way_save($pdo, $me, $pointId, $input['way'])) {
            json_error(t('Der Schlüsselweg ließ sich hier nicht speichern.'), 403);
        }
        json_response(point_keyings_for($pdo, (int) $me['id'], $pointId));
    }
    $walk = $input['walk'] ?? null;
    if ($pointId <= 0 || !is_array($walk)) {
        json_error(t('Der Schlüsselweg fehlt.'));
    }
    $confirmed = isset($input['confirmed']) && is_string($input['confirmed']) && trim($input['confirmed']) !== ''
        ? $input['confirmed'] : null;
    if (!point_keying_save($pdo, $me, $pointId, $walk, $confirmed)) {
        json_error(t('Der Schlüsselweg ließ sich hier nicht speichern.'), 403);
    }
    json_response(point_keyings_for($pdo, (int) $me['id'], $pointId));
}

json_error(t('Methode nicht unterstützt.'), 405);
