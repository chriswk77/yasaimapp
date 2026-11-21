<?php
/**
 * Listen teilen — a list's share, set at the list (migration 0160).
 *
 *   GET  list_recipients.php?list=42          who gets it: recipients, every
 *                                         friend and own group, the fields
 *   POST list_recipients.php {list, recipients: [{type, id, rights, filter}]}
 *                                         save the whole set, as the window
 *                                         „Liste bearbeiten" holds it
 *   POST list_recipients.php {action: 'look', list, color, symbol}
 *                                         my colour and shape for a list
 *                                         somebody shares with me
 *   POST list_recipients.php {action: 'admin_seen', container}
 *                                         the „Listenadministrator" window
 *                                         was seen
 *
 * NOT „share" IN THE ADDRESS: content blockers carry rules against social
 * share buttons that reach far (the Freigaben tab was invisible on
 * the maintainer's desktop for that word, 20.08.2026 — pages/administration.php),
 * and the page's ids and files avoid it for the same reason.
 *
 * THE RULE LIVES IN includes/list_shares.php — only the owner shares, only
 * his own root list, only with friends and his own groups. This file only
 * reads the request and answers.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/list_shares.php';

$userId = (int) $me['id'];

if (!list_shares_ready($pdo)) {
    json_error(t('Listen teilen braucht die Migration 0160 — sie ist noch nicht eingespielt.'), 503);
}

try {
    if ($method === 'GET') {
        $listId = (int) ($_GET['list'] ?? 0);
        json_response(list_share_overview($pdo, $userId, $listId));
    }
    if ($method !== 'POST') {
        json_error(t('Methode nicht unterstützt.'), 405);
    }

    $action = (string) ($input['action'] ?? 'save');
    if ($action === 'look') {
        $listId = (int) ($input['list'] ?? 0);
        $color  = isset($input['color']) && $input['color'] !== '' ? (string) $input['color'] : null;
        $symbol = isset($input['symbol']) && $input['symbol'] !== '' ? (string) $input['symbol'] : null;
        shared_look_set($pdo, $userId, $listId, $color, $symbol);
        json_response(['list' => $listId, 'look' => $color === null && $symbol === null ? null : ['color' => $color, 'symbol' => $symbol]]);
    }
    if ($action === 'admin_seen') {
        list_share_admin_notice_seen($pdo, $userId, (int) ($input['container'] ?? 0));
        json_response(['ok' => true]);
    }
    if ($action !== 'save') {
        json_error(t('Unbekannte Aktion.'));
    }

    $listId     = (int) ($input['list'] ?? 0);
    $recipients = is_array($input['recipients'] ?? null) ? $input['recipients'] : [];
    $result     = list_share_save($pdo, $userId, $listId, $recipients);
    json_response($result + list_share_overview($pdo, $userId, $listId));
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 422);
}
