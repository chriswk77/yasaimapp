<?php
/**
 * Tests excursions, stage 1 (create and manage).
 *
 *   php tests/excursions.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE LEADER CHECK. Every write goes through excursion_of_leader —
 *   if it fell, anyone could rename, re-window or delete anyone's
 *   excursion. The rights of later stages (guest points, photos) will
 *   hang on the same row, so this one check carries them all.
 *
 *   THE WINDOW. Guests may collect only inside it, and the QR access of
 *   stage 2 ends with it — a window that ends before it begins would be
 *   an excursion that never exists and never says why.
 *
 *   THE TOKEN. Born with the row and unique: it will be the excursion's
 *   identity towards guests. Two excursions sharing one token would pour
 *   two groups of strangers into one pot.
 *
 * Everything created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/excursions.php';
require_once __DIR__ . '/../includes/friends.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

/** Calls and says whether it was rejected — as in friends.php. */
function rejected(callable $do, ?string &$reason = null): bool
{
    try {
        $do();
        return false;
    } catch (InvalidArgumentException $e) {
        $reason = $e->getMessage();
        return true;
    }
}

if (!excursions_ready($pdo)) {
    echo "Migration 0068 is missing — nothing to check.\n";
    exit(1);
}

// Three test accounts: a leader, a friend-to-be, a stranger.
$accounts = [];
foreach (['pruef-lena', 'pruef-till', 'pruef-carla'] as $who) {
    $pdo->prepare(
        "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
         VALUES (?, '', 'PRUEF', ?, 'user', NOW())"
    )->execute([$who . '@pruefung.local', ucfirst(substr($who, 6))]);
    $accounts[$who] = (int) $pdo->lastInsertId();
}
[$lena, $till, $carla] = array_values($accounts);

$form = static fn(array $more = []): array => $more + [
    'name'           => 'PRUEF Herbstexkursion',
    'starts_at'      => '2026-09-05T09:00',
    'ends_at'        => '2026-09-05T16:00',
    'photos_allowed' => '1',
    'photo_limit'    => '',
];

try {
    echo "Creating:\n";
    check(rejected(fn() => excursion_create($pdo, $lena, $form(['name' => '  ']))),
        'without a name nothing comes into being');
    check(rejected(fn() => excursion_create($pdo, $lena, $form(['ends_at' => '2026-09-05T08:00']))),
        'a window that ends before it begins is rejected');
    check(rejected(fn() => excursion_create($pdo, $lena, $form(['starts_at' => 'irgendwann']))),
        'an unreadable time is rejected, not swallowed');
    check(rejected(fn() => excursion_create($pdo, $lena, $form(['photo_limit' => '-3']))),
        'a negative photo measure is rejected — empty is the way to mean the default');

    $id = excursion_create($pdo, $lena, $form());
    $e  = excursion_of_leader($pdo, $lena, $id);
    check($e['name'] === 'PRUEF Herbstexkursion' && $e['starts_at'] === '2026-09-05 09:00:00',
        'name and window arrive as entered, the time normalized to DATETIME');
    check($e['photo_limit'] === null,
        'an empty photo measure is stored as NULL — the default, not a zero');
    check(strlen((string) $e['join_token']) === 32,
        'the join token is born with the row');

    $second = excursion_create($pdo, $lena, $form(['name' => 'PRUEF Zweite']));
    $tokens = $pdo->query("SELECT COUNT(DISTINCT join_token) FROM map_excursions
                            WHERE id IN ($id, $second)")->fetchColumn();
    check((int) $tokens === 2, 'two excursions carry two different tokens');

    echo "The leader check:\n";
    check(rejected(fn() => excursion_of_leader($pdo, $till, $id)),
        'a stranger does not even get to read via the leader path');
    check(rejected(fn() => excursion_save($pdo, $till, $id, $form(['name' => 'Gekapert']))),
        'a stranger cannot save');
    check(rejected(fn() => excursion_delete($pdo, $till, $id)),
        'a stranger cannot delete');

    echo "Saving:\n";
    excursion_save($pdo, $lena, $id, $form([
        'name' => 'PRUEF Herbstexkursion II', 'photos_allowed' => '', 'photo_limit' => '20',
    ]));
    $e = excursion_of_leader($pdo, $lena, $id);
    check($e['name'] === 'PRUEF Herbstexkursion II'
          && (int) $e['photos_allowed'] === 0 && (int) $e['photo_limit'] === 20,
        'name and photo policy follow the form');
    excursion_save($pdo, $lena, $id, $form([
        'name' => 'PRUEF Herbstexkursion II',
        'description' => "  Es geht in den Nordhang.\nGummistiefel!  ",
    ]));
    check(excursion_of_leader($pdo, $lena, $id)['description'] === "Es geht in den Nordhang.\nGummistiefel!",
        'the description survives the round trip, trimmed, line breaks kept');
    excursion_save($pdo, $lena, $id, $form(['name' => 'PRUEF Herbstexkursion II']));
    check(excursion_of_leader($pdo, $lena, $id)['description'] === null,
        'and cleared it is NULL again — most excursions need no prose');

    echo "The phase:\n";
    check(excursion_phase($e, '2026-09-05 08:00:00') === 'geplant'
          && excursion_phase($e, '2026-09-05 12:00:00') === 'läuft'
          && excursion_phase($e, '2026-09-05 17:00:00') === 'beendet',
        'geplant, läuft, beendet — measured against the window, one comparison per side');

    echo "Co-leaders (stage 1b):\n";
    check(rejected(fn() => excursion_leader_add($pdo, $lena, $id, $till)),
        'a co-leader must be a FRIEND of the main leader');
    friendship_request($pdo, $lena, $till);
    $fId = (int) $pdo->query(
        "SELECT id FROM map_friends WHERE from_user_id = $lena AND to_user_id = $till"
    )->fetchColumn();
    friendship_accept($pdo, $till, $fId);
    check(in_array($till, friend_ids($pdo, $lena), true),
        'pre-check: the friendship stands');
    check(rejected(fn() => excursion_leader_add($pdo, $till, $id, $till)),
        'only the main leader appoints — not the candidate himself');
    check(rejected(fn() => excursion_leader_add($pdo, $lena, $id, $lena)),
        'the main leader is not his own co-leader');
    excursion_leader_add($pdo, $lena, $id, $till);
    excursion_leader_add($pdo, $lena, $id, $till); // twice is just a double click
    check(count(excursion_leaders($pdo, $id)) === 1,
        'appointing a friend works, twice stays single');

    excursion_save($pdo, $till, $id, $form(['name' => 'PRUEF Herbstexkursion III']));
    check(excursion_of_leader($pdo, $till, $id)['name'] === 'PRUEF Herbstexkursion III',
        'a co-leader may save — the one widened check carries it');
    check(rejected(fn() => excursion_delete($pdo, $till, $id)),
        'deleting stays with the main leader');
    check(rejected(fn() => excursion_leader_add($pdo, $till, $id, $carla)),
        'appointing stays with the main leader too');
    $tillsView = excursions_mine($pdo, $till);
    check(count($tillsView) === 1 && (int) $tillsView[0]['is_main'] === 0
          && $tillsView[0]['leader_name'] !== null,
        'the co-leader sees the excursion in his list, marked with its leader');

    check(rejected(fn() => excursion_leader_remove($pdo, $carla, $id, $till)),
        'a third party removes nobody');
    excursion_leader_remove($pdo, $till, $id, $till);
    check(excursion_leaders($pdo, $id) === [],
        'the co-leader may step out himself — the door, not the bucket');
    check(rejected(fn() => excursion_save($pdo, $till, $id, $form())),
        'and after stepping out the editing right is gone');
    excursion_leader_add($pdo, $lena, $id, $till);
    excursion_leader_remove($pdo, $lena, $id, $till);
    check(excursion_leaders($pdo, $id) === [],
        'the main leader removes a co-leader');

    echo "The guest access (stage 2):\n";
    if (!excursion_guests_ready($pdo)) {
        check(false, 'migration 0074 is applied (guest table, point reference, guest account)');
    } else {
        check(excursion_by_token($pdo, 'kein-token') === null
              && excursion_by_token($pdo, str_repeat('0', 32)) === null,
            'an unknown or unreadable token answers null — a stale QR is normal, not a fault');
        $viaToken = excursion_by_token($pdo, (string) excursion_of_leader($pdo, $lena, $id)['join_token']);
        check($viaToken !== null && (int) $viaToken['id'] === $id,
            'the token finds its excursion');

        check(rejected(fn() => excursion_guest_register($pdo, $id, '  ', 'Nachname', ''))
              && rejected(fn() => excursion_guest_register($pdo, $id, 'Vorname', '  ', '')),
            'first AND last name are required — half a guest does not come in');
        check(rejected(fn() => excursion_guest_register($pdo, $id, 'Gast', 'Gast', 'keine-mail')),
            'an unreadable e-mail is rejected instead of silently kept');
        $g1 = excursion_guest_register($pdo, $id, 'PRUEF', 'Paula', 'paula@pruefung.local');
        // A stranger with Paula's address and no proof is NOT Paula (security review, 23.09.2026).
        $gX = excursion_guest_register($pdo, $id, 'PRUEF', 'Fremd', 'paula@pruefung.local');
        check($gX !== $g1, 'the same e-mail WITHOUT proof is a new guest — the address alone takes over nobody');
        $pdo->prepare('DELETE FROM map_excursion_guests WHERE id = ?')->execute([$gX]);
        $g2 = excursion_guest_register($pdo, $id, 'PRUEF', 'Paula B.', 'paula@pruefung.local', $g1);
        check($g1 === $g2, 'the same e-mail with this browser\'s proof is the same guest');
        $stmt = $pdo->prepare('SELECT name, first_name, last_name FROM map_excursion_guests WHERE id = ?');
        $stmt->execute([$g1]);
        $row = $stmt->fetch();
        check($row['name'] === 'PRUEF Paula B.'
              && $row['first_name'] === 'PRUEF' && $row['last_name'] === 'Paula B.',
            'the newest wording wins, stored SPLIT — and the display name is composed of the two');
        $g3 = excursion_guest_register($pdo, $id, 'PRUEF', 'Ohne', '');
        $g4 = excursion_guest_register($pdo, $id, 'PRUEF', 'Ohne', '');
        check($g3 !== $g4,
            'without an e-mail every registration is a new guest — the named price of withholding it');

        // A running excursion for the capture test — the window check is
        // the point of it.
        $running = excursion_create($pdo, $lena, $form([
            'name' => 'PRUEF Läuft', 'starts_at' => '2026-08-24T00:00', 'ends_at' => '2026-12-31T23:59',
        ]));
        $runningE = excursion_of_leader($pdo, $lena, $running);
        $gRun = excursion_guest_register($pdo, $running, 'PRUEF', 'Paula', 'paula@pruefung.local');
        $pointId = excursion_point_add($pdo, $runningE, $gRun, 'PRUEF Paula', [
            'lat' => 51.2, 'lon' => 10.1, 'title' => 'Boletus edulis', 'note' => 'am Wegrand',
        ]);
        $stmt = $pdo->prepare('SELECT user_id, excursion_id, excursion_guest_id, title, extra
                                 FROM map_points WHERE id = ?');
        $stmt->execute([$pointId]);
        $p = $stmt->fetch();
        check((int) $p['user_id'] === excursion_guest_user_id($pdo),
            'the find lands in the ONE guest account, not with the leader');
        check((int) $p['excursion_id'] === $running && (int) $p['excursion_guest_id'] === $gRun,
            'and carries the excursion reference everything else will hang on');
        check(str_contains((string) $p['extra'], 'PRUEF Paula'),
            'the guest name fills the finder field — reports need no guest special path');
        check(count(excursion_guest_points($pdo, $running, $gRun)) === 1,
            'the guest sees his own finds on the page');

        /* THE CLOSING PAGE'S SPECIES LIST (13.09.2026): one line per
           species, nothing about anyone. A second find of the same
           species by another guest merges into the line. */
        $twin = excursion_guest_register($pdo, $running, 'PRUEF', 'Zwilling', '');
        $twinPoint = excursion_point_add($pdo, $runningE, $twin, 'PRUEF Zwilling', [
            'lat' => 51.21, 'lon' => 10.11, 'title' => 'Boletus  edulis',
        ]);
        $list = excursion_species_list($pdo, $runningE);
        $line = array_values(array_filter($list, static fn(array $r): bool => $r['name'] === 'Boletus edulis'));
        check(count($line) === 1 && $line[0]['finds'] === 2,
            'the species list merges two finds of one species into one line');
        check(array_keys($line[0]) === ['name', 'german', 'rl', 'finds'],
            'and a line carries name, German name, category and count — no finder, no place');
        point_delete_permanently($pdo, excursion_guest_user_id($pdo), $twinPoint);
        // The report and the file export take their finds from
        // export_points() — until 12.09.2026 it knew no excursions, and the
        // guests' rows Organisieren let one tick fell through silently.
        require_once __DIR__ . '/../includes/export.php';
        $reported = export_points($pdo, $lena, [$pointId]);
        check(count($reported) === 1 && (int) $reported[0]['id'] === $pointId
              && ($reported[0]['owner'] ?? '') === 'PRUEF Paula',
            'the report gets the guest find the leader ticked, named after the guest');

        check(rejected(fn() => excursion_point_add($pdo, $e, $gRun, 'PRUEF Paula',
                ['lat' => 51.2, 'lon' => 10.1, 'title' => 'Zu früh'])),
            'outside the window nothing comes into being — checked at every save, not only at the door');

        echo "The guest as an actor (stage 3):\n";
        unset($_SESSION['excursion_actor']);
        check(excursion_actor($pdo) === null, 'without a session there is no actor');

        // On an excursion that is OVER the session grants nothing — and is
        // cleared, not tolerated. (The fixture's window, 05.09.2026, lies
        // behind today.)
        $_SESSION['excursion_actor'] = ['excursion_id' => $id, 'guest_id' => $g1];
        check(excursion_actor($pdo) === null && !isset($_SESSION['excursion_actor']),
            'an actor after the window is void and the stale session is cleared');

        /* AHEAD OF THE WINDOW THE ACTOR STANDS (13.09.2026, „den
           Link kann man doch auch vorher verschicken, und man kann sich
           dann vorher die Karte runterladen"): the guest may be at the
           door and on the map days before — what he cannot do is add a
           find, and that is the save's own check, not the door's. */
        $ahead = excursion_create($pdo, $lena, $form([
            'name' => 'PRUEF Voraus', 'starts_at' => '2037-05-01T09:00', 'ends_at' => '2037-05-01T16:00',
        ]));
        $aheadE = excursion_of_leader($pdo, $lena, $ahead);
        $gAhead = excursion_guest_register($pdo, $ahead, 'PRUEF', 'Früh', 'frueh@pruefung.local');
        check(excursion_joinable($aheadE) && excursion_joinable($runningE) && !excursion_joinable(excursion_of_leader($pdo, $lena, $id)),
            'the door is open ahead of the window and inside it, shut after it');
        $_SESSION['excursion_actor'] = ['excursion_id' => $ahead, 'guest_id' => $gAhead];
        $early = excursion_actor($pdo);
        check($early !== null && (int) $early['excursion']['id'] === $ahead,
            'an actor ahead of the window stands — he came for the map');
        check(rejected(fn() => excursion_point_add($pdo, $aheadE, $gAhead, 'PRUEF Früh',
                ['lat' => 51.2, 'lon' => 10.1, 'title' => 'Vor dem Start'])),
            'and still cannot add a find before the start');
        $_SESSION['excursion_mapping'] = $ahead;
        check((int) (excursion_mapping_current($pdo)['id'] ?? 0) === $ahead,
            'the registered participant who joined ahead keeps his mapping key too');
        $_SESSION['excursion_mapping'] = $id;
        check(excursion_mapping_current($pdo) === null && !isset($_SESSION['excursion_mapping']),
            'after the window it is cleared, as before');
        unset($_SESSION['excursion_mapping']);
        excursion_delete($pdo, $lena, $ahead);   // the count at the end expects the fixtures alone

        $_SESSION['excursion_actor'] = ['excursion_id' => $running, 'guest_id' => $gRun];
        $actor = excursion_actor($pdo);
        check($actor !== null && $actor['name'] === 'PRUEF Paula'
              && (int) $actor['excursion']['id'] === $running,
            'inside the window the actor stands, with his name and his excursion');

        check(excursion_guest_point($pdo, $actor, $pointId) !== null,
            'the actor reaches his own find');
        $otherGuest = excursion_guest_register($pdo, $running, 'PRUEF', 'Anderer', '');
        $otherPoint = excursion_point_add($pdo, $runningE, $otherGuest, 'PRUEF Anderer', [
            'lat' => 51.3, 'lon' => 10.2, 'title' => 'Fremder Fund',
        ]);
        check(excursion_guest_point($pdo, $actor, $otherPoint) === null,
            "another guest's find on the SAME excursion stays out of reach");
        check(excursion_guest_point($pdo, $actor, 999999999) === null,
            'an unknown id likewise');
        check(count(excursion_guest_points_full($pdo, $actor)) === 1,
            'the full payload holds only the own finds — the guest account is an archive, not his');

        check(excursion_fields_sift([
            'Substrat / Wirt' => ' Fagus ', 'Bestimmungssicherheit' => 'erfunden',
            'Frei erfunden' => 'x',
        ]) === ['Substrat / Wirt' => 'Fagus'],
            'the field sift keeps the set, trims, and drops fabricated names and options');

        unset($_SESSION['excursion_actor']);

        echo "The excursion buckets (map cache):\n";
        /* THE SHAPE IS THE CONTRACT, and this is the check that was
           missing when it broke: the first excursion_bucket_points handed
           out a bare list per key instead of {finger, points}. The device
           stored a record with neither, the row counted six and the map
           showed none — silently, on every load anew. pointStore.put()
           reads exactly these two names; whoever changes the shape here
           changes it there. */
        if (bucket_ready($pdo)) {
            $status = excursion_bucket_status($pdo, $running, $lena);
            check($status['finger'] !== [] && $status['counts'] !== [],
                'the status names fingerprints and counts');
            $bucketKey = array_key_first($status['finger']);
            $answer = excursion_bucket_points($pdo, $lena, $running, [$bucketKey]);
            $set = $answer['buckets'][$bucketKey] ?? null;
            check(is_array($set) && array_key_exists('finger', $set)
                    && array_key_exists('points', $set),
                'every delivered bucket is {finger, points} — the shape put() stores');
            check(($set['finger'] ?? '') === $status['finger'][$bucketKey],
                'and its fingerprint is the very one the status promised');
            check(count($set['points'] ?? []) === $status['counts'][$bucketKey],
                'the points match the promised count');
            check(($set['points'][0]['editable'] ?? false) === true
                    && ($set['points'][0]['excursion_id'] ?? 0) === $running,
                'each point is editable and names its excursion');
            check(excursion_bucket_points($pdo, $carla, $running, [$bucketKey])['buckets'] === [],
                'a non-leader gets no buckets at all');
        } else {
            check(false, 'bucket_ready — migration 0038 is applied');
        }

        echo "The photo gate (stage 6a):\n";
        // Fake photo rows suffice — the gate counts, it never opens files.
        $seedPhoto = function (int $pointId) use ($pdo): int {
            $pdo->prepare("INSERT INTO map_photos (user_id, point_id, filename, original_name,
                            width, height, bytes, sort_order, created_at)
                           VALUES (?, ?, ?, 'pruef.jpg', 10, 10, 100, 0, NOW())")
                ->execute([excursion_guest_user_id($pdo), $pointId, bin2hex(random_bytes(8)) . '.jpg']);
            return (int) $pdo->lastInsertId();
        };
        $withLimit = $runningE;
        $withLimit['photos_allowed'] = 1;
        $withLimit['photo_limit'] = 2;
        excursion_photo_gate($pdo, $withLimit, $gRun);
        check(true, 'below the measure the gate lets through');
        $seedPhoto($pointId);
        $seedPhoto($pointId);
        check(rejected(fn() => excursion_photo_gate($pdo, $withLimit, $gRun), $why)
              && str_contains((string) $why, 'lösch eines deiner Fotos'),
            'at the measure the refusal names the way out — delete one of YOUR OWN');
        check(excursion_guest_photo_count($pdo, $running, $otherGuest) === 0,
            "the measure is PER PARTICIPANT — the other guest's count stays untouched");
        excursion_photo_gate($pdo, $withLimit, $otherGuest);
        check(true, 'and he still gets through the gate');
        $off = $withLimit;
        $off['photos_allowed'] = 0;
        check(rejected(fn() => excursion_photo_gate($pdo, $off, $gRun), $why)
              && str_contains((string) $why, 'ausgeschaltet'),
            'photos off names the leader as the reason');
        $pdo->exec('UPDATE map_points SET deleted_at = NOW() WHERE id = ' . (int) $pointId);
        check(excursion_guest_photo_count($pdo, $running, $gRun) === 0,
            'photos on trashed finds do not count — tidying frees room');
        $pdo->exec('UPDATE map_points SET deleted_at = NULL WHERE id = ' . (int) $pointId);
        $pdo->exec('DELETE FROM map_photos WHERE point_id = ' . (int) $pointId);

        echo "The leader's view (stage 5):\n";
        require_once __DIR__ . '/../includes/organize_content.php';
        $guestUser = excursion_guest_user_id($pdo);
        check(excursion_point_leader_scope($pdo, $lena, $pointId) === $guestUser,
            'the main leader gets the owner scope of a guest find');
        excursion_leader_add($pdo, $lena, $running, $till);
        check(excursion_point_leader_scope($pdo, $till, $pointId) === $guestUser,
            'the co-leader likewise — the right hangs on the excursion, not the account');
        check(excursion_point_leader_scope($pdo, $carla, $pointId) === null,
            'a stranger gets nothing — indistinguishable from an unknown point');

        check(organize_field_set($pdo, $lena, [$pointId], 'title', 'Xerocomus badius', false) === 1,
            'the leader renames a guest find through the ordinary Organisieren action');
        check(organize_field_set($pdo, $lena, [$pointId], 'Substrat / Wirt', 'Fagus', false) === 1,
            'and fills its find fields');
        check(organize_field_set($pdo, $carla, [$pointId], 'title', 'Gekapert', false) === 0,
            'a stranger changes nothing — the id resolves to no point for him');

        echo "Filing one's own finds into an excursion afterwards (12.09.2026):\n";
        {
            // A find of the leader's from before the excursion, and one
            // of a stranger's — only the first may move.
            $late   = point_create($pdo, $lena, ['lat' => 51.3, 'lon' => 10.3, 'title' => 'PRUEF Nachtrag',
                                                 'found_at' => '2026-08-01 10:00:00']);
            $carlas = point_create($pdo, $carla, ['lat' => 51.3, 'lon' => 10.3, 'title' => 'PRUEF Fremd']);
            $done = organize_excursion_apply($pdo, $lena, [$late, $carlas], $running);
            $q = $pdo->prepare('SELECT excursion_id FROM map_points WHERE id = ?');
            $q->execute([$late]);   $lateNow   = $q->fetchColumn();
            $q->execute([$carlas]); $carlasNow = $q->fetchColumn();
            check($done['n'] === 1 && (int) $lateNow === $running && $carlasNow === null,
                'the leader files his own find, the stranger\'s stays untouched');
            check($done['outside'] === 1, 'a find outside the window is filed and counted, not refused');
            check(rejected(fn() => organize_excursion_apply($pdo, $carla, [$carlas], $running)),
                'whoever does not lead the excursion is refused');
            check(organize_excursion_apply($pdo, $lena, [$late], $running)['n'] === 0,
                'filing again changes nothing');
            check(organize_excursion_apply($pdo, $lena, [$late], null)['n'] === 1,
                'the empty target takes the find out again');
            $q->execute([$late]);
            check($q->fetchColumn() === null, 'and the reference is gone');
            $pdo->exec('DELETE FROM map_points WHERE id IN (' . (int) $late . ', ' . (int) $carlas . ')');
        }

        check(organize_to_trash($pdo, $carla, [$pointId]) === 0,
            'a stranger deletes nothing either');
        check(organize_to_trash($pdo, $lena, [$pointId]) === 1,
            "the leader tidies his excursion — into the GUEST account's trash");
        $stmt = $pdo->prepare('SELECT user_id, deleted_at FROM map_points WHERE id = ?');
        $stmt->execute([$pointId]);
        $trashed = $stmt->fetch();
        check((int) $trashed['user_id'] === $guestUser && $trashed['deleted_at'] !== null,
            'nothing is beyond recall, and the find never changed accounts');
        excursion_leader_remove($pdo, $lena, $running, $till);

        echo "The trash per excursion (stage 6b):\n";
        // $pointId lies in the trash since the leader tidied above.
        $meLena  = ['id' => $lena, 'role' => 'user'];
        $meCarla = ['id' => $carla, 'role' => 'user'];
        $meAdmin = ['id' => $carla, 'role' => 'admin'];
        $blocks = excursion_trash($pdo, $meLena);
        check(count($blocks) === 1 && count($blocks[0]['points']) === 1
              && $blocks[0]['points'][0]['guest_name'] === 'PRUEF Paula',
            "the leader sees his excursion's trash, each find with its guest's name");
        check(excursion_trash($pdo, $meCarla) === [],
            'a stranger sees no excursion trash at all');
        $adminBlocks = excursion_trash($pdo, $meAdmin);
        check(count($adminBlocks) >= 1
              && array_filter($adminBlocks, fn($b) => (int) $b['excursion']['id'] === $running) !== [],
            'the operator sees every excursion — the concept\'s second eye');

        check(excursion_point_trash_scope($pdo, $meCarla, $pointId) === null,
            'a stranger gets no trash scope');
        check(excursion_point_trash_scope($pdo, $meAdmin, $pointId) === $guestUser,
            'the operator does — but only for excursion points');
        $lenasOwnPoint = point_create($pdo, $lena, ['lat' => 51.0, 'lon' => 10.0, 'title' => 'PRUEF Eigenfund']);
        check(excursion_point_trash_scope($pdo, $meAdmin, $lenasOwnPoint) === null,
            "an ordinary point never resolves — the admin does not reach into strangers' trashes");

        point_restore($pdo, excursion_point_trash_scope($pdo, $meLena, $pointId), $pointId);
        check(excursion_trash($pdo, $meLena) === [],
            'restored is alive again and out of the trash view');
        organize_to_trash($pdo, $lena, [$pointId]);
        $seedPhoto($pointId);
        point_delete_permanently($pdo, excursion_point_trash_scope($pdo, $meLena, $pointId), $pointId);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM map_points WHERE id = ?');
        $stmt->execute([$pointId]);
        $gone = (int) $stmt->fetchColumn() === 0;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM map_photos WHERE point_id = ?');
        $stmt->execute([$pointId]);
        check($gone && (int) $stmt->fetchColumn() === 0,
            'removing for good takes the find AND its photos along — nothing orphaned');

        echo "The attribution (stage 7):\n";
        if (!excursion_attribution_ready($pdo)) {
            check(false, 'migration 0075 is applied');
        } else {
            // Paula becomes an account — carla plays her, with the e-mail
            // in a different case: the claim compares lowercased.
            $pdo->exec("UPDATE map_users SET email = 'PAULA@pruefung.local' WHERE id = $carla");
            // A living find of hers before the claim — stage 6b took the
            // first one for good, and the co-ownership hook needs one.
            $preClaim = excursion_point_add($pdo, $runningE, $gRun, 'PRUEF Paula', [
                'lat' => 51.5, 'lon' => 10.5, 'title' => 'Vor der Zuschreibung']);
            // THE ADDRESS ALONE CLAIMS NOTHING (security review, 23.09.2026):
            // carla carries Paula's address, but her browser proves no guest.
            check(excursion_claim($pdo, $carla, 'PAULA@pruefung.local') === 0,
                'an account with a guest\'s address but no proof ties nothing');
            // Paula was a guest on BOTH test excursions, and this browser
            // joined as her both times — one claim ties both rows.
            require_once __DIR__ . '/../includes/secret.php';
            $paulaRows = $pdo->query("SELECT id, excursion_id FROM map_excursion_guests WHERE email = 'paula@pruefung.local' AND user_id IS NULL")->fetchAll();
            foreach ($paulaRows as $pr) {
                $_COOKIE[excursion_guest_cookie((int) $pr['excursion_id'])] = app_sign((string) $pr['id']);
            }
            check(excursion_claim($pdo, $carla, 'PAULA@pruefung.local') === 2,
                'with this browser\'s proof the login claim ties every guest row at once');
            check(excursion_claim($pdo, $carla, 'paula@pruefung.local') === 0,
                'a second login ties nothing anew — idempotent');
            // Step 5 of co-ownership (11.09.2026): the participant becomes
            // a co-owner of the finds he made as a guest.
            require_once __DIR__ . '/../includes/point_owners.php';
            $q = $pdo->prepare('SELECT p.id FROM map_points p JOIN map_excursion_guests g ON g.id = p.excursion_guest_id
                                 WHERE g.user_id = ? AND p.deleted_at IS NULL');
            $q->execute([$carla]);
            $hers = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
            $owned = array_filter($hers, static fn(int $id): bool => point_owner_is($pdo, $id, $carla));
            check(in_array($preClaim, $hers, true) && count($owned) === count($hers),
                'the claim makes her a co-owner of every find she made as a guest (' . count($hers) . ')');
            $parts = excursions_participated($pdo, $carla);
            $runningPart = array_values(array_filter($parts,
                fn($p) => (int) $p['id'] === $running))[0] ?? null;
            check(count($parts) === 2 && $runningPart !== null
                  && $runningPart['guest_name'] === 'PRUEF Paula',
                'the participant sees his excursions, each with the name he mapped under there');
            check(excursions_participated($pdo, $till) === [],
                'whoever left no e-mail is not found — the named price of withholding it');
            $hersReported = export_points($pdo, $carla, [$preClaim]);
            check(count($hersReported) === 1 && (int) $hersReported[0]['id'] === $preClaim,
                'the participant gets the finds he made as a guest into his own report');

        // Another person, another browser: Paula's proofs do not travel along.
        foreach (array_keys($_COOKIE) as $k) {
            if (str_starts_with((string) $k, 'excursion_guest_')) unset($_COOKIE[$k]);
        }
        echo "The registered participant (stage 9):\n";
            $tillUser = $pdo->query("SELECT * FROM map_users WHERE id = $till")->fetch();
            $joined = excursion_join_as_user($pdo, $runningE, $tillUser);
            check($joined > 0, 'a logged-in participant joins without a form');
            $stmt = $pdo->prepare('SELECT user_id, email FROM map_excursion_guests WHERE id = ?');
            $stmt->execute([$joined]);
            $row = $stmt->fetch();
            check((int) $row['user_id'] === $till,
                'and his row is tied to his account from the first second — no later claim needed');
            check(excursion_join_as_user($pdo, $runningE, $tillUser) === $joined,
                'twice is the same row, not a second identity');

            // His find stays HIS — only the reference travels with it, and
            // that is what the leader's right hangs on.
            $ownFind = point_create($pdo, $till, [
                'lat' => 51.25, 'lon' => 10.15, 'title' => 'PRUEF Teilnehmerfund',
                'excursion_id' => $running,
            ]);
            $stmt = $pdo->prepare('SELECT user_id, excursion_id FROM map_points WHERE id = ?');
            $stmt->execute([$ownFind]);
            $p = $stmt->fetch();
            check((int) $p['user_id'] === $till && (int) $p['excursion_id'] === $running,
                "the find stays in the participant's own account and carries the excursion");
            check(excursion_point_leader_scope($pdo, $lena, $ownFind) === $till,
                'the leader may edit it — the right hangs on the reference, not on the guest account');
            check(excursion_point_leader_scope($pdo, $carla, $ownFind) === null,
                'a stranger may not');
            $pdo->exec('DELETE FROM map_points WHERE id = ' . (int) $ownFind);

            // Stage 6b removed Paula's first find for good — a fresh one
            // for the participant checks (the window still runs).
            $laterPoint = excursion_point_add($pdo, $runningE, $gRun, 'PRUEF Paula', [
                'lat' => 51.21, 'lon' => 10.11, 'title' => 'Später Fund',
            ]);
            check(point_owner_is($pdo, $laterPoint, $carla),
                'a find made under the tied guest row afterwards is co-owned from birth');
            check(excursion_point_participant_scope($pdo, $carla, $laterPoint) === $guestUser,
                'the participant reaches his own old find');
            check(excursion_point_participant_scope($pdo, $carla, $otherPoint) === null,
                "another guest's find on the same excursion stays out of reach");
            check(organize_field_set($pdo, $carla, [$laterPoint], 'note', 'mein Fund von damals', false) === 1,
                'and he tends it through the ordinary Organisieren action — no leader needed');

            echo "The row pencil (organize_point_save):\n";
            $_POST = ['title' => ' Später Fund, bestimmt ', 'note' => '',
                'found_at' => '2026-08-24T18:30',
                'fields' => ['Substrat / Wirt' => 'Picea', 'Frei erfunden' => 'x',
                             'Bestimmungssicherheit' => 'geraten', 'Finder' => '']];
            organize_point_save($pdo, $carla, $laterPoint);
            $saved = point_load($pdo, $guestUser, $laterPoint);
            check($saved['title'] === 'Später Fund, bestimmt'
                  && $saved['note'] === null
                  && str_starts_with((string) $saved['found_at'], '2026-08-24 18:30'),
                'title, note and find time save in one piece — trimmed, emptied, parsed');
            $savedExtra = (array) $saved['extra'];
            check(($savedExtra['Substrat / Wirt'] ?? '') === 'Picea'
                  && !isset($savedExtra['Frei erfunden'])
                  && ($savedExtra['Bestimmungssicherheit'] ?? '') === '',
                'an excursion find takes only the fixed set, a choice only its own options');
            check(($savedExtra['Finder'] ?? '') === 'PRUEF Paula',
                'the finder never falls empty — the registration\'s one purpose survives the form');
            $_POST = ['title' => 'Gekapert'];
            check(rejected(fn() => organize_point_save($pdo, $till, $laterPoint)),
                'whoever holds no right saves nothing — same sentence as for an unknown point');

            $_POST = ['title' => 'PRUEF Eigenfund II', 'note' => 'am Hang',
                'found_at' => '', 'fields' => ['Mein freies Feld' => 'darf sein']];
            organize_point_save($pdo, $lena, $lenasOwnPoint);
            $ownSaved = point_load($pdo, $lena, $lenasOwnPoint);
            check($ownSaved['title'] === 'PRUEF Eigenfund II'
                  && $ownSaved['found_at'] === null
                  && (((array) $ownSaved['extra'])['Mein freies Feld'] ?? '') === 'darf sein',
                'one\'s own point keeps the liberality — any field name, and empty clears the time');
            $_POST = [];

            $pdo->exec('DELETE FROM map_points WHERE id = ' . (int) $laterPoint);
            $pdo->exec("UPDATE map_users SET email = 'pruef-carla@pruefung.local' WHERE id = $carla");
        }

        $pdo->exec('DELETE FROM map_points WHERE id IN (' . (int) $pointId . ',' . (int) $otherPoint . ')');
        excursion_delete($pdo, $lena, $running);
    }

    echo "Deleting:\n";
    /* THE THREE FATES (27.08.2026): an unclaimed guest find
       goes for good, a claimed one moves to its user, a leader's own find
       merely loses the reference. Built as a fixture right here, because
       the fates only show themselves together. */
    if (excursion_guests_ready($pdo) && excursion_attribution_ready($pdo)) {
        $doomed = excursion_guest_register($pdo, $id, 'PRUEF', 'Weg', '');
        $kept   = excursion_guest_register($pdo, $id, 'PRUEF', 'Carla', 'pruef-carla@pruefung.local');
        // The claim, as the login would set it.
        $pdo->prepare('UPDATE map_excursion_guests SET user_id = ? WHERE id = ?')
            ->execute([$carla, $kept]);
        $guestUser = excursion_guest_user_id($pdo);

        $gone = point_create($pdo, $guestUser, ['lat' => 51.0, 'lon' => 10.0,
            'title' => 'PRUEF weg', 'excursion_id' => $id, 'excursion_guest_id' => $doomed]);
        $moved = point_create($pdo, $guestUser, ['lat' => 51.0, 'lon' => 10.1,
            'title' => 'PRUEF zu Carla', 'excursion_id' => $id, 'excursion_guest_id' => $kept]);
        $mine = point_create($pdo, $lena, ['lat' => 51.0, 'lon' => 10.2,
            'title' => 'PRUEF Leiterfund', 'excursion_id' => $id]);
    }

    excursion_leader_add($pdo, $lena, $id, $till);
    excursion_delete($pdo, $lena, $id);
    check(excursion_leaders($pdo, $id) === [],
        'deleting the excursion takes its co-leader rows along');

    if (excursion_guests_ready($pdo) && excursion_attribution_ready($pdo)) {
        $row = static function (int $pointId) use ($pdo): array|false {
            $q = $pdo->prepare('SELECT user_id, excursion_id, excursion_guest_id
                                  FROM map_points WHERE id = ?');
            $q->execute([$pointId]);
            return $q->fetch();
        };
        check($row($gone) === false,
            'an unclaimed guest find is gone for good — no trash, as the dialog says');
        $m = $row($moved);
        check($m !== false && (int) $m['user_id'] === $carla,
            'a claimed guest find now belongs to its user');
        check($m !== false && $m['excursion_id'] === null && $m['excursion_guest_id'] === null,
            'and carries no excursion reference any more');
        $l = $row($mine);
        check($l !== false && (int) $l['user_id'] === $lena && $l['excursion_id'] === null,
            'a leader\'s own find stays his and merely loses the reference');
        check((int) $pdo->query("SELECT COUNT(*) FROM map_excursion_guests
                                  WHERE excursion_id = $id")->fetchColumn() === 0,
            'the guest rows die with their excursion');
    }
    check(rejected(fn() => excursion_of_leader($pdo, $lena, $id)),
        'deleted is gone — the leader path says so');
    check(count(excursions_mine($pdo, $lena)) === 1,
        'the second one remains untouched');
} finally {
    $ids = implode(',', $accounts);
    if (excursion_guests_ready($pdo)) {
        $pdo->exec("DELETE FROM map_points WHERE excursion_id IN
                     (SELECT id FROM map_excursions WHERE leader_user_id IN ($ids))");
        $pdo->exec("DELETE FROM map_excursion_guests WHERE excursion_id IN
                     (SELECT id FROM map_excursions WHERE leader_user_id IN ($ids))");
    }
    $pdo->exec("DELETE FROM map_excursion_leaders WHERE user_id IN ($ids)
                 OR excursion_id IN (SELECT id FROM map_excursions WHERE leader_user_id IN ($ids))");
    $pdo->exec("DELETE FROM map_excursions WHERE leader_user_id IN ($ids)");
    $pdo->exec("DELETE FROM map_points WHERE user_id IN ($ids)");
    $pdo->exec("DELETE FROM map_friends WHERE from_user_id IN ($ids) OR to_user_id IN ($ids)");
    $pdo->exec("DELETE FROM map_users WHERE id IN ($ids)");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
