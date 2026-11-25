<?php
/**
 * Who sees one's picture archives, and how (migration 0184,
 * includes/picture_archives.php: „who sees what").
 *
 *   POST archive_viewers.php {friend: <id> | 0, sees: bool}          sees the pictures at all
 *   POST archive_viewers.php {friend: <id> | 0, spots: bool}         … with their find spots
 *   POST archive_viewers.php {friend: <id> | 0, add: {kind, name}}   hide a genus or species from him
 *   POST archive_viewers.php {friend: <id> | 0, remove: {kind, name}} show it again
 *
 * friend 0 is „Neue Freunde". kind is genus or species. ONE CHANGE PER
 * CALL, saved at once — the ticks and the filter window have no save
 * button (02.11.2026: „bei solchen Haken würde ich ein Autosave
 * machen"). The answer is the friend's answer as stored, so the row draws
 * exactly that.
 *
 * Not „share" in the name: content blockers hide what is called so
 * (CLAUDE.md, trap 6).
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/picture_archives.php';

if ($me === null) {
    json_error(t('Dafür musst du angemeldet sein.'), 403);
}
if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
if (!archive_viewers_ready($pdo)) {
    json_error(t('Das braucht die Migration 0184 — sie ist noch nicht eingespielt.'), 503);
}
$userId = (int) $me['id'];
$friend = (int) ($input['friend'] ?? 0);
$friend = $friend > 0 ? $friend : null;

$change = [];
foreach (['sees', 'spots'] as $key) {
    if (array_key_exists($key, $input)) $change[$key] = (bool) $input[$key];
}
$add = is_array($input['add'] ?? null) ? $input['add'] : null;
$remove = is_array($input['remove'] ?? null) ? $input['remove'] : null;
if ($add !== null || $remove !== null) {
    $audience = archive_audience($pdo, $userId);
    $current = $friend === null ? $audience['new']
        : (array_values(array_filter($audience['friends'], static fn($f) => $f['id'] === $friend))[0] ?? null);
    if ($current === null) {
        json_error(t('Mit dieser Person bist du nicht mehr befreundet.'), 404);
    }
    $hidden = $current['hidden'];
    $one = $add ?? $remove;
    $list = ($one['kind'] ?? '') === 'species' ? 'species' : 'genera';
    if ($add !== null) {
        $name = archive_hidden_name($pdo, $userId, (string) ($add['name'] ?? ''), $list === 'species');
        if ($name === null) {
            json_error($list === 'species'
                ? t('„%s" kenne ich nicht als Art. Schreib Gattung und Art, zum Beispiel „Inocybe geophylla".', trim((string) ($add['name'] ?? '')))
                : t('„%s" kenne ich nicht als Gattung. Schreib nur die Gattung, zum Beispiel „Inocybe".', trim((string) ($add['name'] ?? ''))), 422);
        }
        $hidden[$list][] = $name;
    } else {
        $gone = mb_strtolower(trim((string) ($remove['name'] ?? '')));
        $hidden[$list] = array_values(array_filter($hidden[$list], static fn($n) => mb_strtolower($n) !== $gone));
    }
    $change['hidden'] = $hidden;
}
if ($change === []) {
    json_error(t('Nichts zu speichern.'));
}

try {
    $answer = archive_audience_set($pdo, $userId, $friend, $change);
} catch (InvalidArgumentException $e) {
    json_error(error_for_user($e), 404);
}
json_response(['friend' => $friend ?? 0, 'answer' => $answer]);
