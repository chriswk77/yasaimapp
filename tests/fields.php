<?php
/**
 * Tests the extra fields of the lists — in particular the field type "Auswahl".
 *
 *   php tests/fields.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   A SELECT WITHOUT VALUES IS NOT ONE. A select field without options (or
 *   on a server before migration 0046) must honestly become a text field —
 *   otherwise the point would show a field with nothing to choose in it.
 *
 *   THE OPTIONS ARE CLEANED, but their ORDER stays: it is the order in the
 *   select field, and somebody thought that order through
 *   (makroskopisch before mikroskopisch before unsicher is a scale, not an
 *   alphabet).
 *
 *   UNKNOWN TYPES fall back to text instead of being stored — a type no
 *   interface knows would be an invisible field.
 *
 * Everything created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-felder@pruefung.local', '', 'PRUEF', 'Felder', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

try {
    $listId = list_create($pdo, $userId, [
        'name' => 'PRUEF Feldliste', 'color' => '#336699', 'symbol' => 'circle',
        'fields' => [
            ['name' => 'PRUEF Zustand', 'type' => 'choice',
             'options' => ['makroskopisch sicher', 'mikroskopisch sicher', ' unsicher ', 'unbestimmt', 'makroskopisch sicher', '']],
            ['name' => 'Beschreibung', 'type' => 'multiline'],
            ['name' => 'Leere Auswahl', 'type' => 'choice', 'options' => ['', '   ']],
            ['name' => 'Komisch', 'type' => 'schieberegler'],
        ],
    ]);

    echo "Saving and loading:\n";
    $lists = lists_load($pdo, $userId);
    $fields = [];
    foreach ($lists[0]['fields'] as $f) {
        $fields[$f['name']] = $f;
    }

    check($fields['PRUEF Zustand']['type'] === 'choice',
        'the select field comes back as a select');
    check($fields['PRUEF Zustand']['options']
        === ['makroskopisch sicher', 'mikroskopisch sicher', 'unsicher', 'unbestimmt'],
        'the options are cleaned (duplicate, empties, edges), the order stands');
    check($fields['Beschreibung']['type'] === 'multiline'
          && $fields['Beschreibung']['options'] === null,
        'text fields carry no options');
    check($fields['Leere Auswahl']['type'] === 'text',
        'a select without usable values honestly becomes a text field');
    check($fields['Komisch']['type'] === 'text',
        'an unknown type falls back to text');

    echo "The value on the point stays text:\n";
    $pointId = point_create($pdo, $userId, [
        'lat' => 51.0, 'lon' => 10.0, 'title' => 'PRUEF Fund',
        'extra' => ['PRUEF Zustand' => 'mikroskopisch sicher'],
        'lists' => [$listId],
    ]);
    $extra = json_decode((string) $pdo->query(
        "SELECT extra FROM map_points WHERE id = $pointId"
    )->fetchColumn(), true);
    check(($extra['PRUEF Zustand'] ?? null) === 'mikroskopisch sicher',
        'the chosen value lies as ordinary text in the extra block');
} finally {
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
