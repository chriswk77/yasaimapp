<?php
/**
 * Recorded tracks — what the recorder app sends.
 *
 *   GET    tracks.php               the list, WITHOUT positions
 *   GET    tracks.php?id=<client>   one track WITH its positions
 *   POST   tracks.php               store or replace (client_id decides)
 *   POST   tracks.php  {action: 'split', id: <client>, spot: [lat, lon]}
 *                                   cut a track in two at the spot — the
 *                                   server finds the segment it lies on
 *                                   and cuts ON the line (track_split_between);
 *                                   {at: <index>} without a spot cuts at
 *                                   that position of the original, the
 *                                   older form. The map's doing, not the
 *                                   app's (track_split explains the ids)
 *   POST   tracks.php  {action: 'assign', id: <client>, tour_id?, tour?, excursion_id?}
 *                                   file the track under an outing by hand
 *
 * A stored track MAY carry 'tour' and 'tour_id' from the sender (the same
 * twelve-character id the points mint, migration 0079); what it does not
 * carry is derived from the finds of that day and the excursions the user
 * is on (track_assignment_derive).
 *   DELETE tracks.php?id=<client>   delete
 *
 * ADDRESSED BY client_id, NOT BY THE DATABASE ID. The app mints that id
 * itself before the first position, out in the woods where nothing can be
 * asked of a server (see migration 0084). It therefore knows it from the
 * start and never has to remember a second, server-side number — and a
 * repeated upload lands on the same track instead of creating a twin.
 *
 * THE APP COMES WITH A BEARER TOKEN, the browser with its session — both
 * routes end in the same $me (auth.php). Nothing here needs to know which
 * one it was; the pairing in the administration mints the token, this
 * endpoint only sees a logged-in user.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/tracks.php';

$userId = (int) $me['id'];

if (!tracks_ready($pdo)) {
    json_error(t('Wegstrecken brauchen die Migration 0084 — sie ist noch nicht eingespielt.'), 503);
}

/* WHICH DEVICE SENT THIS. Read from the token the request came with, not
   from the body: whoever sends may name their track, but not which device
   it came from — that is the one field only the server can honestly
   fill. */
$device = '';
$token  = token_from_request();
if ($token !== null) {
    $stmt = $pdo->prepare('SELECT device FROM map_tokens WHERE token_hash = ?');
    $stmt->execute([hash('sha256', $token)]);
    $device = (string) ($stmt->fetchColumn() ?: '');
}

$clientId = trim((string) ($_GET['id'] ?? ''));

try {
    switch ($method) {
        case 'GET':
            json_response($clientId !== ''
                ? track_one($pdo, $userId, $clientId)
                : ['tracks' => tracks_list($pdo, $userId)]);

        case 'POST':
            if (($input['action'] ?? '') === 'split') {
                json_response(track_split($pdo, $userId,
                    trim((string) ($input['id'] ?? '')), (int) ($input['at'] ?? -1),
                    is_array($input['spot'] ?? null) ? $input['spot'] : null));
            }
            // {action: 'assign', id, tour_id?, tour?, excursion_id?} — the
            // hand correction of the outing (track_assign): only the keys
            // sent change, null clears.
            if (($input['action'] ?? '') === 'assign') {
                json_response(track_assign($pdo, $userId, trim((string) ($input['id'] ?? '')), $input));
            }
            json_response(track_store($pdo, $userId, $input ?? [], $device));

        case 'DELETE':
            if ($clientId === '') {
                json_error(t('Ohne id lässt sich nichts löschen.'), 422);
            }
            track_remove($pdo, $userId, $clientId);
            json_response(['deleted' => $clientId]);

        default:
            json_error(t('Diese Methode kennt der Endpunkt nicht.'), 405);
    }
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 422);
}
