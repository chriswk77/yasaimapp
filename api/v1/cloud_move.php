<?php
/**
 * One step of a move to another cloud, carried by the open Cloud tab.
 *
 *   POST cloud_move.php   → the move's state after up to CLOUD_MOVES_SECONDS
 *
 * THE TAB CARRIES WHILE IT IS OPEN (the choice, 26.09.2026): a
 * handful of pictures are over in a minute or two instead of waiting for
 * the five-minute cron. When the tab closes, the cron bundle carries on
 * (cronjobs/every_5_minutes.php) — the SAME cloud_moves_run(), under the SAME
 * lock, so tab and cron never carry one picture twice. A step that finds
 * the lock taken says 'busy' and the tab asks again a little later.
 *
 * The switch itself, and why the old cloud's key is kept: includes/
 * cloud_moves.php.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';

/* NOT FOR DEVICE TOKENS, like every door to the cloud (api/v1/cloud.php
   says why): a lost phone must not be a lost cloud. */
if (token_from_request() !== null) {
    json_error(t('Ein Geräte-Zugang reicht für die Cloud nicht — bitte im Browser anmelden.'), 403);
}
if ($method !== 'POST') {
    json_error(t('Diese Adresse nimmt nur POST an.'), 405);
}

require_once __DIR__ . '/../../includes/cloud_moves.php';

$userId = (int) $me['id'];
$move   = cloud_move_open($pdo, $userId);
if ($move === null) {
    json_response(['status' => 'none']);
}

// The cron job's own lock (cronjobs/_frame.php, cron_lock) — one carrier.
$lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
$lock->execute(['map_cron_cloud_moves']);
if ((int) $lock->fetchColumn() !== 1) {
    json_response(['busy' => true] + cloud_move_state($pdo, $move));
}

@set_time_limit(CLOUD_MOVES_SECONDS + 60);
try {
    cloud_moves_run($pdo, microtime(true) + CLOUD_MOVES_SECONDS, $userId);
} finally {
    $pdo->query('SELECT RELEASE_LOCK("map_cron_cloud_moves")');
}

$move = cloud_move_get($pdo, (int) $move['id']) ?? $move;
$state = cloud_move_state($pdo, $move);

/* THE END, SAID ONCE — as a toast on the reload the tab does next, the
   same way the consent page's outcome arrives (cloud_render picks it up
   exactly once). A move the cron finished while the tab was closed says
   so in the tile's footnote instead. */
if ($state['status'] === 'done') {
    $_SESSION['cloud_message'] = ['success', match ($state['moved']) {
        0       => t('Fertig. %s ist jetzt deine Cloud.', $state['to']),
        1       => $state['files'] > 0 ? t('Umzug fertig. Die Datei liegt jetzt in %s.', $state['to'])
                                           : t('Umzug fertig. Das Bild liegt jetzt in %s.', $state['to']),
        default => $state['files'] > 0 ? t('Umzug fertig. Alle %d Dateien liegen jetzt in %s.', $state['moved'], $state['to'])
                                           : t('Umzug fertig. Alle %d Bilder liegen jetzt in %s.', $state['moved'], $state['to']),
    }];
}

json_response($state);
