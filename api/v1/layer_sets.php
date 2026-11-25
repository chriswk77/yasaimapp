<?php
/**
 * „Ebenen" — the work on a set of layers, kept (migration 0191; see
 * includes/layer_sets.php, the tool is assets/js/blend.js).
 *
 *   GET  layer_sets.php?point=<find>              the sets one works on there
 *   POST layer_sets.php multipart {action: mask, point, key, image}
 *                                                 one layer's mask into its
 *                                                 picture's cloud
 *   POST layer_sets.php {point, layers: [{key, shown, mask}, …]}
 *                                                 keep the set (ground first)
 *   POST layer_sets.php {action: delete, id}      give a set up, masks and all
 *
 * THE MASK BEFORE THE ROW, each in a request of its own — a mask is a file
 * of up to two megabytes, and the row that names it is only written once
 * it lies in the cloud.
 *
 * Online only, as the tool: it works on the originals out of the cloud.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/layer_sets.php';

if ($me === null) {
    json_error(t('Dafür musst du angemeldet sein.'), 403);
}
$userId = (int) $me['id'];

if ($method === 'GET') {
    $pointId = (int) ($_GET['point'] ?? 0);
    json_response([
        // Before migration 0191 the tool works, and says that nothing is kept.
        'ready' => layer_sets_ready($pdo),
        'sets'  => $pointId > 0 ? layer_sets_of($pdo, $userId, $pointId) : [],
    ]);
}
if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
if (!layer_sets_ready($pdo)) {
    json_error(t('Der Arbeitsstand der Ebenen braucht die Migration 0191 — sie ist noch nicht eingespielt.'), 503);
}

try {
    if (($input['action'] ?? '') === 'delete') {
        $stmt = $pdo->prepare('SELECT * FROM map_layer_sets WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) ($input['id'] ?? 0), $userId]);
        $row = $stmt->fetch();
        if ($row !== false) {
            layer_set_drop($pdo, $userId, (int) $row['point_id'], layer_set_shape($row));
        }
        json_response(['deleted' => true]);
    }

    $pointId = (int) ($input['point'] ?? 0);
    // One's own find — the tool is offered nowhere else, and a mask goes into one's own cloud.
    $stmt = $pdo->prepare('SELECT 1 FROM map_points WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$pointId, $userId]);
    if ($stmt->fetchColumn() === false) {
        json_error(t('Fund nicht gefunden.'), 404);
    }

    if (($input['action'] ?? '') === 'mask') {
        $file = $_FILES['image'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            json_error(t('Die Maske kam nicht als Bild an.'), 422);
        }
        try {
            layer_mask_store($pdo, $userId, $pointId, (string) ($input['key'] ?? ''), (string) file_get_contents($file['tmp_name']));
        } catch (RuntimeException $e) {
            error_log('layer_sets.php mask (' . $pointId . ' ' . ($input['key'] ?? '') . '): ' . $e->getMessage());
            json_error(t('Die Maske ließ sich nicht in der Cloud speichern.'), 502);
        }
        json_response(['stored' => true]);
    }

    $layers = layer_sets_clean($input['layers'] ?? null);
    if ($layers === null) {
        json_error(t('Unbrauchbare Ebenen.'), 422);
    }
    json_response(['set' => layer_set_save($pdo, $userId, $pointId, $layers)]);
} catch (InvalidArgumentException $e) {
    json_error(error_for_user($e), 422);
}
