<?php
/**
 * Tests the Aufsammlungen (migration 0174, includes/collections.php).
 *
 *   php tests/collections.php    (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   AN AUFSAMMLUNG IS NO FIND: no find number of the series, its own number
 *   for the box, counted per collecting year.
 *
 *   A SPECIES TAKES PLACE, DAY AND FINDER FROM ITS AUFSAMMLUNG — the find
 *   date of a culture find is the collecting day — and cannot be moved or
 *   redated on its own. If that falls, the species of one dung sample end
 *   up with the day they were microscoped, and every season statistic
 *   lies.
 *
 *   THE AUFSAMMLUNG TAKES ITS SPECIES ALONG when it moves or is redated.
 *
 *   THE SAME SPECIES MAY COME TWICE, and only one's own Aufsammlung counts.
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

if (!collections_ready($pdo)) {
    echo "Migration 0174 missing — nothing to check.\n";
    exit(1);
}

$accounts = [];
foreach (['pruef-anna', 'pruef-ben'] as $who) {
    $pdo->prepare("INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
                   VALUES (?, '', 'PRUEF', ?, 'user', NOW())")->execute([$who . '@pruefung.local', ucfirst(substr($who, 6))]);
    $accounts[$who] = (int) $pdo->lastInsertId();
}
[$anna, $ben] = array_values($accounts);
$row = static fn(int $id): array => $pdo->query("SELECT * FROM map_points WHERE id = $id")->fetch();
$extra = static fn(int $id): array => json_decode((string) $row($id)['extra'], true) ?: [];

try {
    // A species list, so a find there would draw a number — the
    // Aufsammlung must not.
    require_once __DIR__ . '/../includes/find_number.php';
    $fungi = list_create($pdo, $anna, ['name' => 'PRUEF Pilze', 'is_species' => true]);

    echo "The Aufsammlung:\n";
    // Berlin, not Hesse: no DGM1 fetch that slows the test down.
    $a1 = point_create($pdo, $anna, ['lat' => 52.51, 'lon' => 13.40, 'is_collection' => true,
        'found_at' => '2026-09-03 10:00:00', 'cultured_at' => '2026-09-04', 'lists' => [$fungi],
        'extra' => ['Finder' => 'Anna Sammlerin', 'Substrat / Wirt' => 'Capreolus capreolus', 'Begleitvegetation' => 'Fagus sylvatica']]);
    $a2 = point_create($pdo, $anna, ['lat' => 52.52, 'lon' => 13.41, 'is_collection' => true,
        'found_at' => '2026-09-10 10:00:00', 'lists' => [$fungi]]);
    $old = point_create($pdo, $anna, ['lat' => 52.52, 'lon' => 13.41, 'is_collection' => true,
        'found_at' => '2025-06-01 10:00:00']);
    check((int) $row($a1)['is_collection'] === 1 && $row($a1)['find_number'] === null,
        'it is marked and draws no find number, though it lies in a species list');
    check($row($a1)['collection_number'] === 'PA-A-2026-01' && $row($a2)['collection_number'] === 'PA-A-2026-02'
        && $row($old)['collection_number'] === 'PA-A-2025-01', 'its own number for the box, counted per collecting year, the initials in front');
    check($row($a1)['cultured_at'] === '2026-09-04' && $row($a1)['culture_ended_at'] === null, 'in culture since, still running');

    echo "The species from it:\n";
    $s1 = point_create($pdo, $anna, ['lat' => 1, 'lon' => 1, 'title' => 'Ascobolus furfuraceus',
        'collection_id' => $a1, 'identified_at' => '2026-09-14', 'found_at' => '2026-09-14 20:00:00',
        'lists' => [$fungi], 'extra' => ['Finder' => 'jemand anders', 'Bestimmer' => 'Ben']]);
    $s2 = point_create($pdo, $anna, ['lat' => 1, 'lon' => 1, 'title' => 'Ascobolus furfuraceus',
        'collection_id' => $a1, 'identified_at' => '2026-09-28', 'lists' => [$fungi]]);
    $r1 = $row($s1);
    check((float) $r1['lat'] === 52.51 && (float) $r1['lon'] === 13.40, "the place is the Aufsammlung's, whatever came along");
    check($r1['found_at'] === '2026-09-03 10:00:00', 'the find date is the collecting day, not the day of microscopy');
    check($extra($s1)['Finder'] === 'Anna Sammlerin' && $extra($s1)['Bestimmer'] === 'Ben',
        'the finder is the collector, the determiner stays free');
    check(($extra($s1)['Substrat / Wirt'] ?? '') === 'Capreolus capreolus' && ($extra($s1)['Begleitvegetation'] ?? '') === 'Fagus sylvatica',
        'the substrate comes along as a start, the plants around as a copy');
    check($r1['identified_at'] === '2026-09-14' && (int) $r1['collection_id'] === $a1, 'identified on its own day, pointing home');
    check($r1['find_number'] !== null || find_number_pattern($pdo, $anna) === null, 'a species is a find and draws its number');
    check($s2 !== $s1 && (int) $row($s2)['collection_id'] === $a1, 'the same species may come a second time');

    echo "Saving a species:\n";
    point_update($pdo, $anna, $s1, ['lat' => 50.0, 'lon' => 9.0, 'found_at' => '2026-10-01 10:00:00',
        'extra' => ['Finder' => 'umgeschrieben', 'Bestimmer' => 'Carla', 'Begleitvegetation' => 'Picea abies',
                    'Substrat / Wirt' => 'Ascobolus furfuraceus'], 'identified_at' => '2026-09-15']);
    $r1 = $row($s1);
    check((float) $r1['lat'] === 52.51 && $r1['found_at'] === '2026-09-03 10:00:00', 'it cannot be moved or redated on its own');
    check($extra($s1)['Finder'] === 'Anna Sammlerin' && $extra($s1)['Bestimmer'] === 'Carla'
          && $extra($s1)['Begleitvegetation'] === 'Fagus sylvatica',
        'the finder and the plants around hold (strict copies), the determiner changes');
    check($extra($s1)['Substrat / Wirt'] === 'Ascobolus furfuraceus', 'the substrate is the species\' own — a fungus on a fungus');
    check($r1['identified_at'] === '2026-09-15', 'the identification day can be corrected');

    echo "Saving the Aufsammlung:\n";
    point_update($pdo, $anna, $a1, ['lat' => 52.60, 'lon' => 13.50, 'found_at' => '2026-09-02 09:00:00',
        'extra' => ['Finder' => 'Anna S.', 'Substrat / Wirt' => 'Capreolus capreolus', 'Begleitvegetation' => ''],
        'culture_ended_at' => '2026-10-10']);
    foreach ([$s1, $s2] as $s) {
        $r = $row($s);
        check((float) $r['lat'] === 52.6 && $r['found_at'] === '2026-09-02 09:00:00' && $extra($s)['Finder'] === 'Anna S.'
              && ($extra($s)['Begleitvegetation'] ?? '') === '',
            "species #$s followed the Aufsammlung's place, day, finder and plants around — an emptied one too");
    }
    check($extra($s1)['Substrat / Wirt'] === 'Ascobolus furfuraceus', 'a species\' own substrate is not overwritten');
    check($row($a1)['culture_ended_at'] === '2026-10-10', 'the culture is ended');
    point_update($pdo, $anna, $a2, ['title' => '']);
    check($row($a2)['title'] === 'Aufsammlung PA-A-2026-02', 'saved without a name, it is called by its number again');

    echo "Somebody else's Aufsammlung:\n";
    $refused = false;
    try {
        point_create($pdo, $ben, ['lat' => 1, 'lon' => 1, 'title' => 'PRUEF fremd', 'collection_id' => $a1]);
    } catch (InvalidArgumentException $e) {
        $refused = true;
    }
    check($refused, "nobody hangs a species into somebody else's Aufsammlung unasked");

    /* ANNA SHARES THE DUNG, BEN DETERMINES (15.10.2026): her
       list with the tick „eigene Punkte setzen" for him, and his species
       hangs into her Aufsammlung — his find, her place, day and finder. */
    require_once __DIR__ . '/../includes/list_shares.php';
    if (list_shares_ready($pdo) && friends_ready($pdo)) {
        friendship_request($pdo, $anna, $ben);
        $fId = (int) $pdo->query("SELECT id FROM map_friends WHERE from_user_id = $anna AND to_user_id = $ben")->fetchColumn();
        friendship_accept($pdo, $ben, $fId);
        list_share_save($pdo, $anna, $fungi, [['type' => 'friend', 'id' => $ben, 'rights' => ['own_points']]]);
        $bens = point_create($pdo, $ben, ['lat' => 1, 'lon' => 1, 'title' => 'Coprinopsis radiata',
            'collection_id' => $a1, 'identified_at' => '2026-09-20', 'lists' => [$fungi], 'extra' => ['Bestimmer' => 'Ben']]);
        $rb = $row($bens);
        check((int) $rb['user_id'] === $ben && (int) $rb['collection_id'] === $a1, "with „eigene Punkte setzen\" Ben's species hangs into her Aufsammlung, and it is his");
        check((float) $rb['lat'] === (float) $row($a1)['lat'] && $rb['found_at'] === $row($a1)['found_at']
            && ($extra($bens)['Finder'] ?? '') === ($extra($a1)['Finder'] ?? null) && $extra($bens)['Bestimmer'] === 'Ben',
            "place, day and finder are hers, the determiner his");
        point_update($pdo, $anna, $a1, ['lat' => 52.61, 'lon' => 13.51]);
        check((float) $row($bens)['lat'] === 52.61, "moving her Aufsammlung takes his species along");
        check(point_owner_is($pdo, $a1, $ben), 'and with his species he became a co-owner of the Aufsammlung');

        echo "Stepping out („So ist das gerecht\"):\n";
        // Ben steps out of a second one: his species go with him, hers stay.
        $a3 = point_create($pdo, $anna, ['lat' => 52.53, 'lon' => 13.43, 'is_collection' => true,
            'found_at' => '2026-09-12 10:00:00', 'lists' => [$fungi]]);
        $hers3 = point_create($pdo, $anna, ['lat' => 1, 'lon' => 1, 'title' => 'Pilobolus', 'collection_id' => $a3, 'lists' => [$fungi]]);
        $his3 = point_create($pdo, $ben, ['lat' => 1, 'lon' => 1, 'title' => 'Sordaria', 'collection_id' => $a3, 'lists' => [$fungi]]);
        point_owner_leave($pdo, $a3, $ben);
        check($row($his3)['deleted_at'] !== null && $row($hers3)['deleted_at'] === null && $row($a3)['deleted_at'] === null,
            'a co-owner who steps out takes his own species into his trash; the Aufsammlung and hers stay');

        // Anna deletes the first one: it goes to Ben with his species, hers go with her.
        point_delete($pdo, $anna, $a1);
        check((int) $row($a1)['user_id'] === $ben && $row($a1)['deleted_at'] === null,
            'the holder who deletes hands the Aufsammlung to the co-owner');
        check($row($s1)['deleted_at'] !== null && $row($s2)['deleted_at'] !== null && $row($bens)['deleted_at'] === null,
            'her species went into her trash, not to him as a copy; his stay');
        /* BEN HANGS HIS MICRO PICTURE AT ANNA'S SPECIES
           (15.10.2026) — a picture makes him co-owner of the species
           (point_photo_attached → point_owner_add), and with it of the
           Aufsammlung. Anna deletes the Aufsammlung: both go to Ben. */
        $a5 = point_create($pdo, $anna, ['lat' => 52.54, 'lon' => 13.44, 'is_collection' => true,
            'found_at' => '2026-09-13 10:00:00', 'lists' => [$fungi]]);
        $hers5 = point_create($pdo, $anna, ['lat' => 1, 'lon' => 1, 'title' => 'Thecotheus', 'collection_id' => $a5, 'lists' => [$fungi]]);
        point_owner_add($pdo, $hers5, $ben);
        check(point_owner_is($pdo, $hers5, $ben) && point_owner_is($pdo, $a5, $ben),
            'a co-owner of a species is a co-owner of its Aufsammlung too');
        point_delete($pdo, $anna, $a5);
        check((int) $row($a5)['user_id'] === $ben && $row($a5)['deleted_at'] === null
            && (int) $row($hers5)['user_id'] === $ben && $row($hers5)['deleted_at'] === null,
            'Anna deletes: the species they share and the Aufsammlung both go to Ben, nothing hangs at a trashed one');

        // And Ben, alone now, deletes it: into his trash, with his species.
        point_delete($pdo, $ben, $a1);
        check($row($a1)['deleted_at'] !== null && $row($bens)['deleted_at'] !== null,
            'whoever is alone with it deletes it, his species along');
    } else {
        echo "  (list shares or friends missing — the shared case not checked)\n";
    }
    $refused = false;
    try {
        point_create($pdo, $anna, ['lat' => 1, 'lon' => 1, 'title' => 'PRUEF', 'collection_id' => $s1]);
    } catch (InvalidArgumentException $e) {
        $refused = true;
    }
    check($refused, 'nor into a find that is no Aufsammlung');

    echo "To the browser:\n";
    $s4 = point_create($pdo, $anna, ['lat' => 1, 'lon' => 1, 'title' => 'Iodophanus', 'collection_id' => $a2, 'identified_at' => '2026-09-15']);
    $loaded = point_load($pdo, $anna, $s4);
    check(($loaded['collection_id'] ?? null) === $a2 && ($loaded['identified_at'] ?? null) === '2026-09-15'
        && ($loaded['is_collection'] ?? null) === false, 'point_load carries the fields');
    $loadedA = point_load($pdo, $anna, $a2);
    check(($loadedA['is_collection'] ?? null) === true && ($loadedA['collection_number'] ?? null) === 'PA-A-2026-02',
        '… and the Aufsammlung its own');
} finally {
    $idList = implode(',', $accounts);
    foreach (['map_container_state', 'map_container_messages', 'map_container_left', 'map_container_members', 'map_container_lists'] as $table) {
        if (table_exists($pdo, $table)) {
            $pdo->exec("DELETE FROM $table WHERE container_id IN (SELECT id FROM map_containers WHERE owner_user_id IN ($idList))");
        }
    }
    if (table_exists($pdo, 'map_containers')) {
        $pdo->exec("DELETE FROM map_container_state WHERE user_id IN ($idList)");
        $pdo->exec("DELETE FROM map_containers WHERE owner_user_id IN ($idList)");
        $pdo->exec("DELETE FROM map_friends WHERE from_user_id IN ($idList) OR to_user_id IN ($idList)");
    }
    if (table_exists($pdo, 'map_point_owners')) {
        $pdo->exec("DELETE FROM map_point_owners WHERE user_id IN ($idList) OR point_id IN (SELECT id FROM map_points WHERE user_id IN ($idList))");
    }
    $pdo->exec("DELETE FROM map_point_lists WHERE point_id IN (SELECT id FROM map_points WHERE user_id IN ($idList))");
    $pdo->exec("DELETE FROM map_points WHERE user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_lists WHERE user_id IN ($idList)");
    if (table_exists($pdo, 'map_find_numbers')) {
        $pdo->exec("DELETE FROM map_find_numbers WHERE user_id IN ($idList)");
    }
    $pdo->exec("DELETE FROM map_users WHERE id IN ($idList)");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
