<?php
/**
 * Hanging one's own lists into a container.
 *
 *   POST shares.php   {container: 5, lists: [42, 45]}
 *
 * THE WAY EXISTED ONLY IN THE ADMINISTRATION, behind three clicks and a
 * page change: open administration, Gemeinsam, Container, the block, the
 * plus, check, submit. On the map one stands in front of the container and
 * knows exactly which of one's lists belongs in it — that is where the
 * decision falls, so that is where it should be possible
 * (20.08.2026).
 *
 * NOTHING NEW HAPPENS HERE. share_create() is the same function the
 * administration calls, with the same checks: it is one's own list, one is
 * a member, and the branch does not overlap one already hanging. This
 * endpoint is a second door to the same room, not a second room.
 *
 * EACH LIST ON ITS OWN, and the answer says which ones failed. Whoever
 * checks three and has one of them overlap should get the other two hung
 * in — an all-or-nothing would make him check them again one at a time to
 * find out which one it was.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/container.php';

$userId = (int) $me['id'];

if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
if (!container_ready($pdo)) {
    json_error(t('Freigaben brauchen die Migration 0042 — sie ist noch nicht eingespielt.'), 503);
}

$containerId = (int) ($input['container'] ?? 0);
if (!container_visible($pdo, $userId, $containerId)) {
    json_error(t('Kein Zugriff auf diese Freigabe.'), 403);
}

$lists = array_values(array_filter(array_map('intval', (array) ($input['lists'] ?? []))));
if ($lists === []) {
    json_error(t('Keine Liste gewählt.'), 422);
}

$done   = [];
$failed = [];
foreach ($lists as $listId) {
    try {
        share_create($pdo, $userId, $containerId, $listId);
        $done[] = $listId;
    } catch (InvalidArgumentException $e) {
        // The name with it: "Waldbrandflächen: overlaps" is an answer,
        // "overlaps" alone is a riddle when three were checked.
        $stmt = $pdo->prepare('SELECT name FROM map_lists WHERE id = ? AND user_id = ?');
        $stmt->execute([$listId, $userId]);
        $name = $stmt->fetchColumn();
        $failed[] = ($name !== false ? '„' . $name . '": ' : '') . $e->getMessage();
    }
}

json_response(['attached' => $done, 'error' => $failed]);
