<?php
/**
 * Tests the find fields — the fixed field set of every species list.
 *
 *   php tests/find_fields.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE NAMES ARE TAKEN. An own field "Finder" next to the find field
 *   "Finder" would be two things under one name — list_fields_set
 *   must filter out such doubles, however they are spelled.
 *
 *   THE SPECIES TABLE KNOWS THE FIND FIELDS: Substrat / Wirt and
 *   Begleitvegetation carry species names. If they were missing from
 *   point_species_fields, the host plant of every find would vanish from
 *   the genus search.
 *
 *   WHEN SHARING the find fields come along: list_fields_of_root must
 *   put them first for species lists, otherwise members would not see the
 *   substrate data of foreign finds.
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
require_once __DIR__ . '/../includes/find_fields.php';
require_once __DIR__ . '/../includes/point_species.php';
require_once __DIR__ . '/../includes/container.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-fund@pruefung.local', '', 'PRUEF', 'Fund', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

try {
    echo "The names are taken:\n";
    $listId = list_create($pdo, $userId, [
        'name' => 'PRUEF Funde', 'color' => '#336699', 'symbol' => 'mushroom', 'is_species' => true,
        'fields' => [
            ['name' => 'Finder', 'type' => 'text'],
            ['name' => 'BESTIMMER', 'type' => 'multiline'],
            ['name' => 'Eigenes Feld', 'type' => 'text'],
        ],
    ]);
    $names = array_column(lists_load($pdo, $userId)[0]['fields'], 'name');
    check($names === ['Eigenes Feld'],
        'own fields carrying a find-field name are filtered out, however they are spelled');

    echo "The species table knows the find fields:\n";
    // The species list itself (once a sublist below it, inheriting).
    $subId = $listId;
    $pointId = point_create($pdo, $userId, [
        'lat' => 51.0, 'lon' => 10.0, 'title' => 'Hygrocybe punicea',
        'extra' => ['Substrat / Wirt' => 'Fagus sylvatica'],
        'lists' => [$subId],
    ]);
    $where = point_species_fields($pdo, [$subId]);
    check($where['title'] === true, 'the species checkmark makes the title a species');
    check(in_array('Substrat / Wirt', $where['fields'], true)
          && in_array('Begleitvegetation', $where['fields'], true),
        'the species find fields count among the species fields');

    $stmt = $pdo->prepare("SELECT name FROM map_point_species WHERE point_id = ? AND field = 'Substrat / Wirt'");
    $stmt->execute([$pointId]);
    check($stmt->fetchColumn() === 'Fagus sylvatica',
        'the host plant stands in the species table');

    echo "When sharing they come along:\n";
    $fields = list_fields_of_root($pdo, $subId);
    check(($fields[0]['name'] ?? '') === 'Finder',
        'the find fields come first');
    check(in_array('Eigenes Feld', array_column($fields, 'name'), true),
        'the own fields follow behind');
    $certainty = array_values(array_filter($fields, fn($f) => $f['name'] === 'Bestimmungssicherheit'))[0] ?? null;
    check($certainty !== null && $certainty['type'] === 'choice'
          && $certainty['options'] === ['makroskopisch sicher', 'mikroskopisch sicher', 'genetisch sicher', 'unsicher', 'unbestimmt'],
        'the Bestimmungssicherheit carries its five values as a choice');

    echo "Without the species checkmark everything stays as it was:\n";
    $normalId = list_create($pdo, $userId, ['name' => 'PRUEF Normal', 'color' => '#111111',
                                              'symbol' => 'circle',
                                              'fields' => [['name' => 'Finder', 'type' => 'text']]]);
    $lists = lists_load($pdo, $userId);
    $normal = array_values(array_filter($lists, fn($l) => $l['name'] === 'PRUEF Normal'))[0];
    check(array_column($normal['fields'], 'name') === [],
        'even without the checkmark the find-field name stays blocked — it would collide when the checkmark is set');
    check(list_fields_of_root($pdo, $normalId) === [],
        'without the species checkmark sharing brings no find fields');
} finally {
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
