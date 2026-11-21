<?php
/**
 * A container, as far as it concerns me.
 *
 *   PATCH containers.php?id=5   {own_name: "Erics Pilzstellen"}
 *   PATCH containers.php?id=5   {own_name: null}   back to the original
 *   PATCH containers.php?id=3   {name: "Meine Funde 2026"}   owner only
 *
 * TWO NAMES, AND WHO ASKS DECIDES WHICH ONE. 'own_name' is what only I
 * see; 'name' is what everybody sees, and only the owner may set it. The
 * window offers exactly one of the two — for the owner a private label
 * beside his own name would be a distinction without a difference.
 *
 *   DELETE containers.php?id=5   leave — for oneself alone
 *
 * THE DEPARTURE IS A DELETE AND NOT A PATCH, because from the caller's
 * side that is what it is: after it, this container is gone for me. What
 * happens underneath — a row in map_container_left, a direct membership
 * removed, the hung-in lists unhooked — is the server's business
 * (container_leave, migration 0067).
 *
 * DELETE never removes the container itself. That stays the owner's act
 * and stays in the administration, where its consequences are visible.
 * The owner asking to leave gets a refusal saying so.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/container.php';

$userId      = (int) $me['id'];
$containerId = (int) ($_GET['id'] ?? $input['id'] ?? 0);

if ($method !== 'PATCH' && $method !== 'DELETE') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
if (!container_ready($pdo)) {
    json_error(t('Freigaben brauchen die Migration 0042 — sie ist noch nicht eingespielt.'), 503);
}
if (!container_visible($pdo, $userId, $containerId)) {
    json_error(t('Kein Zugriff auf diese Freigabe.'), 403);
}

if ($method === 'DELETE') {
    try {
        container_leave($pdo, $userId, $containerId);
    } catch (InvalidArgumentException $e) {
        json_error($e->getMessage(), 422);
    }
    json_response(['left' => $containerId]);
}

// array_key_exists and not isset: null is the answer "no own name any
// more", and isset() cannot tell it from "not sent along".
$ownName = array_key_exists('own_name', $input);
$realName = array_key_exists('name', $input);

if (!$ownName && !$realName) {
    json_error(t('Nichts zu ändern.'), 422);
}

try {
    if ($realName) {
        // Refuses for anybody who is not the owner — the check sits in the
        // function, not here: this is a door to it, not the boundary.
        container_rename($pdo, $userId, $containerId, (string) $input['name']);
    }
    if ($ownName) {
        container_own_name($pdo, $userId, $containerId, $input['own_name'] === null
            ? null
            : (string) $input['own_name']);
    }
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 422);
}

// The name back as it now stands — trimmed and cut, and null where it was
// emptied. The window writes exactly this into the field, so what one sees
// is what is stored.
$fresh = ['name' => null, 'own_name' => null];
foreach (container_mine($pdo, $userId) as $c) {
    if ($c['id'] === $containerId) {
        $fresh = ['name' => $c['name'], 'own_name' => $c['own_name']];
    }
}

json_response($fresh);
