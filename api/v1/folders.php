<?php
/**
 * Mappen (migration 0168) — a name that gathers lists in the sidebar.
 *
 *   GET    folders.php          the user's Mappen
 *   POST   folders.php          create {name, client_id?}
 *   PATCH  folders.php?id=3     rename {name}, move {sort_order}
 *   DELETE folders.php?id=3     delete — the lists in it stay and stand
 *                               where the Mappe stood
 *
 * Every answer carries the Mappen AND the lists: deleting a Mappe changes
 * the lists' folder_id, and the browser takes both from one response.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';

$userId = (int) $me['id'];
$both = static fn(): array => ['folders' => folders_load($pdo, $userId), 'lists' => lists_load($pdo, $userId)];

if (!folders_exist($pdo)) {
    json_error(t('Mappen brauchen die Migration 0168 — sie ist noch nicht eingespielt.'), 409);
}

switch ($method) {
    case 'GET':
        json_response($both());

    case 'POST':
        if (trim((string) ($input['name'] ?? '')) === '') {
            json_error(t('Die Mappe braucht einen Namen.'));
        }
        $id = folder_create($pdo, $userId, $input);
        json_response(['id' => $id] + $both(), 201);

    case 'PATCH':
    case 'PUT':
        $id = request_id();
        if (!folder_update($pdo, $userId, $id, $input)) {
            json_error(t('Mappe nicht gefunden oder nichts zu ändern.'), 404);
        }
        json_response($both());

    case 'DELETE':
        $id = request_id();
        if (!folder_delete($pdo, $userId, $id)) {
            json_error(t('Mappe nicht gefunden.'), 404);
        }
        json_response(['deleted' => $id] + $both());

    default:
        json_error(t('Methode nicht unterstützt.'), 405);
}
