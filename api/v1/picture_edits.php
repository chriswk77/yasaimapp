<?php
/**
 * Foreign edits of a picture (migration 0194; see includes/picture_edits.php).
 *
 *   GET  picture_edits.php?kind=photo|cloud&id=<picture>
 *        → { ready, role: owner|editor, open, owner_name, edits: [{id, user_id, name, develop, updated_at}] }
 *          for the photographer and for everyone who may edit it; 404 else
 *   POST {action: save, kind, id, edit?, develop, patches?}
 *                                                   keep one's own edit (a new one without `edit`);
 *                                                   `patches` as at photos.php — into the photographer's cloud
 *   POST {action: delete, edit}                     take one's own edit away
 *   POST {action: open, kind, id, open}             the photographer's switch „Andere dürfen bearbeiten"
 *
 * The answer that someone may not see a picture is the same as that it is
 * not there — as every scope in this project says it.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/picture_edits.php';

if ($me === null) {
    json_error(t('Dafür musst du angemeldet sein.'), 403);
}
$userId = (int) $me['id'];

if ($method === 'GET') {
    $kind = (string) ($_GET['kind'] ?? '');
    $id = (int) ($_GET['id'] ?? 0);
    $scope = picture_edit_scope($pdo, $userId, $kind, $id);
    if ($scope === null) {
        json_error(t('Bild nicht gefunden.'), 404);
    }
    // The photographer's name heads his edits in the editor's bar (photowindow.js, versionsDraw).
    $stmt = $pdo->prepare('SELECT ' . sql_display_name() . ' FROM map_users u WHERE u.id = ?');
    $stmt->execute([$scope['owner']]);
    json_response([
        // Before migration 0194 nobody else may edit, and there is nothing to show.
        'ready' => picture_edits_ready($pdo),
        'role'  => $scope['role'],
        'open'  => $scope['open'],
        'owner_name' => (string) $stmt->fetchColumn(),
        'edits' => picture_edits_of($pdo, $kind, $id),
    ]);
}
if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
if (!picture_edits_ready($pdo)) {
    json_error(t('Fremde Bearbeitungen brauchen die Migration 0194 — sie ist noch nicht eingespielt.'), 503);
}

try {
    switch ((string) ($input['action'] ?? '')) {
        case 'save':
            $edit = isset($input['edit']) ? (int) $input['edit'] : null;
            $kind = (string) ($input['kind'] ?? '');
            $id = (int) ($input['id'] ?? 0);
            $editId = picture_edit_save($pdo, $userId, $kind, $id, $edit ?: null, $input['develop'] ?? null, $input['patches'] ?? null);
            json_response(['edit' => $editId, 'edits' => picture_edits_of($pdo, $kind, $id)]);
        case 'delete':
            json_response(['deleted' => picture_edit_delete($pdo, $userId, (int) ($input['edit'] ?? 0))]);
        case 'open':
            if (!picture_edit_open_set($pdo, $userId, (string) ($input['kind'] ?? ''), (int) ($input['id'] ?? 0), !empty($input['open']))) {
                json_error(t('Bild nicht gefunden.'), 404);
            }
            json_response(['open' => !empty($input['open'])]);
        default:
            json_error(t('Unbekannte Aktion.'), 422);
    }
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    // The photographer's cloud would not take the patches — nothing was kept, the next change tries again.
    error_log('picture_edits.php: ' . $e->getMessage());
    json_error(t('Die entfernten Stellen ließen sich nicht in der Cloud speichern.'), 502);
}
