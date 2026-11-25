<?php
/**
 * Lists.
 *
 *   GET    lists.php          all lists with their point counts
 *   POST   lists.php          create
 *   PATCH  lists.php?id=3     rename, color, symbol, order
 *   DELETE lists.php?id=3     delete (the points in it are preserved)
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';

$userId = (int) $me['id'];

switch ($method) {
    case 'GET':
        json_response(['lists' => lists_load($pdo, $userId), 'folders' => folders_load($pdo, $userId)]);

    case 'POST':
        try {
            $id = list_create($pdo, $userId, $input);
        } catch (InvalidArgumentException $e) {
            json_error(error_for_user($e), 409);
        }
        $lists = lists_load($pdo, $userId);
        $fresh = array_values(array_filter($lists, static fn(array $l) => $l['id'] === $id));
        json_response($fresh[0] ?? ['id' => $id], 201);

    case 'PATCH':
    case 'PUT':
        $id = request_id();
        if ($id <= 0) {
            json_error(t('Keine Kennung angegeben.'));
        }
        try {
            $updated = list_update($pdo, $userId, $id, $input);
        } catch (InvalidArgumentException $e) {
            json_error(error_for_user($e), 409);
        }
        if (!$updated) {
            json_error(t('Liste nicht gefunden oder nichts zu ändern.'), 404);
        }
        json_response(['lists' => lists_load($pdo, $userId), 'folders' => folders_load($pdo, $userId)]);

    case 'DELETE':
        $id = request_id();
        try {
            $result = list_delete($pdo, $userId, $id);
        } catch (InvalidArgumentException $e) {
            json_error(error_for_user($e), 409);
        }
        if ($result === null) {
            json_error(t('Liste nicht gefunden.'), 404);
        }
        // How many points now stand under "Ohne Liste" — the map reports
        // it, so nobody puzzles over where the markers have gone.
        // 'catch_all' carries the ID of a manually created list "Ohne Liste",
        // in case the orphans moved in there (data.php).
        json_response([
            'deleted' => $id,
            'points'    => $result['points'],
            'catch_all' => $result['catch_all'],
            // A joint list went to another member instead (step 3): to whom.
            'handed_over' => $result['handed_over'] ?? null,
            'lists'    => lists_load($pdo, $userId),
        ]);

    default:
        json_error(t('Methode nicht unterstützt.'), 405);
}
