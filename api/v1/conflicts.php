<?php
/**
 * Overwritten changes — the losing versions from field_times.php.
 *
 *   GET  conflicts.php           what nobody has seen yet, newest first
 *   GET  conflicts.php?count=1   only the count (for the bar)
 *   POST conflicts.php           {seen: [ids]} acknowledge
 *
 * IS CALLED conflicts, NOT overridden: _start.php already occupies
 * $overridden for the method envelope, and a file whose name means
 * something else in its own preamble is a trap laid out.
 *
 * RESTORING a version does not go through here but as an ordinary PATCH on
 * points.php: it is a change like any other — with a fresh field time, so
 * all devices see it during the sync. The version pushed aside by it was
 * standing right next to it for the user; it does NOT come here again,
 * otherwise the window would spin in circles. Only when a third device has
 * written in between (what it saw no longer matches) is its version kept — so
 * still nothing gets lost unseen.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';

$userId = (int) $me['id'];

switch ($method) {
    case 'GET':
        if (!empty($_GET['count'])) {
            json_response(['count' => overwritten_count($pdo, $userId)]);
        }
        json_response([
            'open' => overwritten_pending($pdo, $userId),
            'count' => overwritten_count($pdo, $userId),
        ]);
        // no break needed, json_response terminates

    case 'POST':
        $ids = (array) ($input['seen'] ?? []);
        overwritten_seen($pdo, $userId, $ids);
        json_response(['count' => overwritten_count($pdo, $userId)]);

    default:
        json_error(t('Methode nicht unterstützt.'), 405);
}
