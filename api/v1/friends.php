<?php
/**
 * Answering a friend request — from the map.
 *
 * WHY THIS EXISTS. Until 25.08.2026 a request could only be answered in
 * the administration, and it could only be FOUND there: the count stood on
 * a tab, and whoever did not walk past that tab never learned that
 * somebody had asked. The map now shows a badge with the name and two
 * buttons („vielleicht kannst du in der Badge an sich schon ein
 * Annehmen, Ablehnen-Button machen"), and those two buttons need a way
 * back that is not a form on another page.
 *
 * THE LIST COMES WITH THE CHAT POLL, not from here: api/v1/chat.php
 * carries the open requests in its unread answer, because the map asks it
 * every few seconds anyway. This endpoint only WRITES — one request, one
 * answer, nothing to read.
 *
 * NO NEW RIGHTS. Both actions go through friendship_accept() and
 * friendship_remove() from includes/friends.php, the very functions the
 * administration uses; those check that the friendship is mine to answer.
 * Whoever hands in a foreign id gets the same refusal as over there.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/friends.php';

$userId = (int) $me['id'];

if ($method !== 'POST') {
    json_error(t('Nur POST.'), 405);
}

if (!friends_ready($pdo)) {
    json_error(t('Freunde brauchen die Migration 0041 — sie ist noch nicht eingespielt.'), 503);
}

$id     = (int) ($input['id'] ?? 0);
$action = (string) ($input['action'] ?? '');

if ($id <= 0) {
    json_error(t('Welche Anfrage?'), 422);
}

try {
    match ($action) {
        // ACCEPT AND DECLINE ARE THE SAME TWO FUNCTIONS the administration
        // calls. Declining is friendship_remove: the row goes, and the
        // other side can ask again another day — that is what the cross
        // means everywhere in this application, and not "never again".
        'accept'  => friendship_accept($pdo, $userId, $id),
        'decline' => friendship_remove($pdo, $userId, $id),
        // The asker took note of the acceptance (13.09.2026): the badge
        // goes on every device, nothing else changes.
        'seen'    => friendship_seen($pdo, $userId, $id),
        default   => throw new InvalidArgumentException(t('Unbekannte Aktion.')),
    };
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 422);
}

// What is still open afterwards — so the badge can redraw itself without
// waiting for the next poll.
$open = [];
foreach (friends_overview($pdo, $userId)['incoming'] as $row) {
    $open[] = ['id' => (int) $row['id'], 'name' => (string) $row['display_name']];
}

json_response(['friendRequests' => $open, 'friendAccepted' => friends_accepted_unseen($pdo, $userId)]);
