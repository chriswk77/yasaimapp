<?php
/**
 * Tests the shared containers (stage 2 of sharing).
 *
 *   php tests/container.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   MEMBERSHIP IS THE BOUNDARY. Whoever is not a member hangs nothing in
 *   and sees nothing — if this test falls, strangers see strangers' points.
 *
 *   THE GROUP COUNTS LIVE: whoever joins is in the container, whoever
 *   leaves is out — along with their shares. A share from someone who is
 *   no longer a member would be a share to people they never meant.
 *
 *   NO OVERLAP: a branch and its sub-branch in the same container would
 *   mean the same points twice.
 *
 *   THE FIELDS COME FROM THE ROOT, no matter which branch hangs — that is
 *   the rule the members' point window rests on.
 *
 *   THE POINTS CARRY ONLY CONTAINER LISTS: that a point at home still
 *   sits in other lists is none of the members' business.
 *
 *   THE BIRTH FILTER STAYS TIGHT (migration 0050): time range, area and
 *   title rules sieve on EVERY delivery path — the container points as
 *   well as the single access. If that falls, members see finds the
 *   sharer never meant.
 *
 * Everything created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/container.php';
// container.php pulls this in only inside a function — the bucket contract
// below asks both deliveries directly.
require_once __DIR__ . '/../includes/bucket.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

/** Calls and says whether it was rejected. ?string as in sublists.php. */
function rejected(callable $fn, ?string &$reason = null): bool
{
    try {
        $fn();
        return false;
    } catch (InvalidArgumentException $e) {
        $reason = $e->getMessage();
        return true;
    }
}

if (!container_ready($pdo) || !friends_ready($pdo)) {
    echo "Migration 0041/0042 missing — nothing to check.\n";
    exit(1);
}

// Three test accounts: anna shares, ben is a friend, carla comes via the group.
$accounts = [];
foreach (['pruef-anna', 'pruef-ben', 'pruef-carla'] as $who) {
    $pdo->prepare(
        "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
         VALUES (?, '', 'PRUEF', ?, 'user', NOW())"
    )->execute([$who . '@pruefung.local', ucfirst(substr($who, 6))]);
    $accounts[$who] = (int) $pdo->lastInsertId();
}
[$anna, $ben, $carla] = array_values($accounts);

try {
    // Friendships: anna–ben, anna–carla. carla comes via the group later.
    foreach ([$ben, $carla] as $who) {
        friendship_request($pdo, $anna, $who);
        $fId = (int) $pdo->query("SELECT id FROM map_friends WHERE from_user_id = $anna AND to_user_id = $who")->fetchColumn();
        friendship_accept($pdo, $who, $fId);
    }
    $group = group_create($pdo, $anna, 'PRUEF Gruppe');
    group_add($pdo, $anna, $group, $carla);

    /* Anna's list, with a field, points in it. ONE LIST SINCE THE MAPPEN
       (0168): this test once hung a branch with a leaf below a root; lists
       are flat now, and $root and $branch stay as names for the same list
       so the checks below keep reading as they did. */
    require_once __DIR__ . '/../includes/data.php';
    $leaf   = list_create($pdo, $anna, ['name' => 'PRUEF Blatt', 'color' => '#996633', 'symbol' => 'flag']);
    $root   = $leaf;
    $branch = $leaf;
    $pdo->prepare('INSERT INTO map_list_fields (list_id, name, type, is_species, sort_order) VALUES (?, ?, ?, 0, 0)')
        ->execute([$root, 'PRUEF Substrat', 'text']);

    $pointA = point_create($pdo, $anna, ['lat' => 51.1, 'lon' => 10.1, 'title' => 'PRUEF Punkt A', 'lists' => [$leaf]]);
    $pointB = point_create($pdo, $anna, ['lat' => 51.2, 'lon' => 10.2, 'title' => 'PRUEF Punkt B', 'lists' => [$leaf]]);

    echo "Container and members:\n";
    $cId = container_create($pdo, $anna, 'PRUEF Container');
    check(rejected(fn() => container_create($pdo, $anna, 'PRUEF Container')),
        'the same container name under the same owner is rejected');
    check(container_member_ids($pdo, $cId) === [$anna],
        'at the start only the owner is a member');
    check(rejected(fn() => container_add($pdo, $ben, $cId, 'friend', $carla)),
        'only the owner may admit');

    container_add($pdo, $anna, $cId, 'friend', $ben);
    container_add($pdo, $anna, $cId, 'group', $group);
    $ids = container_member_ids($pdo, $cId);
    sort($ids);
    check($ids === [$anna, $ben, $carla],
        'owner, friend and group member together are the members');
    check(container_visible($pdo, $carla, $cId), 'the group member sees the container');

    echo "Shares:\n";
    check(rejected(fn() => share_create($pdo, $ben, $cId, $branch)),
        "someone else's lists cannot be hung in");
    share_create($pdo, $anna, $cId, $branch);
    check(rejected(fn() => share_create($pdo, $anna, $cId, $leaf), $r1),
        'the same list a second time is rejected');

    $shares = container_shares($pdo, $cId);
    check(count($shares) === 1, 'one share sits in the container');
    $f = $shares[0];
    check(array_column($f['lists'], 'id') === [$leaf],
        'the share holds exactly its list');
    check($f['lists'][0]['parent_id'] === null,
        'the list sits at the top of the container, without a parent_id');
    check(array_column($f['fields'], 'name') === ['PRUEF Substrat'],
        "the list's fields come along");

    echo "Points:\n";
    // Additionally put point A into a private list at home.
    $private = list_create($pdo, $anna, ['name' => 'PRUEF Privat', 'color' => '#111111', 'symbol' => 'cross']);
    point_lists_set($pdo, $anna, $pointA, [$leaf, $private]);

    $points = container_points($pdo, $cId);
    check(count($points) === 2, 'both leaf points sit in the container');
    $pA = array_values(array_filter($points, fn($p) => $p['id'] === $pointA))[0] ?? null;
    check($pA !== null && $pA['lists'] === [$leaf],
        'the point carries only its container lists, the private one stays at home');
    check($pA['owner'] === 'PRUEF Anna', 'every point names its owner — by name, not by nick');

    // THE „BEARBEITET" MARK travels with a shared find (06.10.2026): a cloud
    // recording of the holder counts for the member too — it stood at a
    // flat 0 here, and Finn's micrographs never reached the maintainer's sidebar.
    if (table_exists($pdo, 'map_cloud_files')) {
        $pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, name, size, uploaded_at)
                       VALUES (?, ?, "pcloud", "pruef-bearbeitet", "Sporen.jpg", 1, NOW())')->execute([$anna, $pointA]);
        $pA = array_values(array_filter(container_points($pdo, $cId), fn($p) => $p['id'] === $pointA))[0] ?? null;
        check(($pA['recordings'] ?? 0) === 1, 'a shared find with a cloud recording arrives as worked („bearbeitet")');
        $pdo->prepare('DELETE FROM map_cloud_files WHERE fileid = "pruef-bearbeitet"')->execute();
    }

    // -----------------------------------------------------------------
    // THE BUCKET CONTRACT — the one thing here that no error message
    // guards. The device fetches its own stock and that of a container
    // through the SAME code path (map.js), so a key that is spelled
    // differently in one of the two deliveries throws nothing: the client
    // reads undefined, files a bucket with zero points and never asks for
    // the rest. The buckets that did not fit into one draw are then
    // silently missing — under the fingerprint of a bucket that counts as
    // complete. That is not a hypothesis; it was the state until the
    // renaming was carried after the fact into container.php.
    //
    // THE LITERAL NAMES ARE WHAT IS TESTED, not merely that the two
    // agree. A rename that catches both files in one go would keep them
    // equal to each other and still leave the browser empty-handed.
    // Whoever changes one of these four strings changes map.js
    // (response.buckets, response.open) and pointstore.js (satz.finger,
    // satz.points) in the same breath — otherwise this check falls.
    if (bucket_ready($pdo)) {
        echo "The bucket contract (container and own stock speak the same):\n";

        $containerDelivery = container_bucket_points(
            $pdo, $cId, array_keys(container_bucket_fingerprints($pdo, $cId))
        );
        $ownDelivery = bucket_points(
            $pdo, $anna, array_keys(bucket_fingerprints($pdo, $anna))
        );

        foreach (['buckets', 'open'] as $key) {
            check(array_key_exists($key, $containerDelivery),
                "the container delivery carries „$key" . '" — map.js reads it under that name');
            check(array_key_exists($key, $ownDelivery),
                "the own delivery carries „$key" . '" too');
        }

        // Not array_keys() === array_keys(): bucket_points() additionally
        // reports 'points' on its empty early return. What matters is that
        // nothing the browser reads is missing on either side.
        $containerBucket = $containerDelivery['buckets'][array_key_first($containerDelivery['buckets'])] ?? null;
        $ownBucket       = $ownDelivery['buckets'][array_key_first($ownDelivery['buckets'])] ?? null;

        check($containerBucket !== null, 'the container really delivers a bucket to look into');
        foreach (['finger', 'points'] as $key) {
            check($containerBucket !== null && array_key_exists($key, $containerBucket),
                "inside a container bucket sits „$key" . '" — pointstore.js reads it under that name');
            check($ownBucket !== null && array_key_exists($key, $ownBucket),
                "inside an own bucket sits „$key" . '" too');
        }

        // The check that would have caught the damage: with the old key
        // the bucket arrived, but empty — and an empty bucket looks
        // exactly like one whose points really are gone.
        $inBuckets = 0;
        foreach ($containerDelivery['buckets'] as $set) {
            $inBuckets += count($set['points'] ?? []);
        }
        check($inBuckets === 2,
            'both container points lie IN the buckets, not merely beside them');
    }

    if (share_rights_ready($pdo)) {
        echo "Rights (stage 3):\n";
        $fgId = (int) $pdo->query(
            "SELECT id FROM map_container_lists WHERE container_id = $cId AND user_id = $anna"
        )->fetchColumn();

        check(rejected(fn() => share_rights_set($pdo, $ben, $fgId, ['title'])),
            'rights are granted only by the sharer, not by a member');
        check(rejected(fn() => share_rights_set($pdo, $anna, $fgId, ['fliegen'])),
            'an unknown right fails loudly');
        check(rejected(fn() => share_rights_set($pdo, $anna, $fgId, ['field:Gibtsnicht'])),
            'a field: right without a matching root field likewise');

        // Without rights: seeing yes, writing no.
        $access = point_foreign_rights($pdo, $ben, $pointA);
        check($access !== null && $access['rights'] === [],
            'without granted rights the member sees the point but may do nothing');
        check($access['owner'] === $anna, 'the owner is stated alongside');
        check($access['visible_lists'] === [$leaf],
            "visible are only the point's container lists");
        check(point_foreign_rights($pdo, $anna, $pointA) === null,
            'for the owner herself the rights path is no path at all');
        check(rejected(fn() => foreign_input_sift($pdo, $pointA, [], ['title' => 'x'])),
            'the sieve rejects a title without the title right');

        share_rights_set($pdo, $anna, $fgId,
            ['title', 'delete', 'field:PRUEF Substrat']);
        $access = point_foreign_rights($pdo, $ben, $pointA);
        sort($access['rights']);
        check($access['rights'] === ['delete', 'field:PRUEF Substrat', 'title'],
            'granted rights arrive at the member');

        // The sieve: the allowed passes, the forbidden is loud, extra only
        // the released keys — the rest stays stored.
        point_update($pdo, $anna, $pointA,
            ['extra' => ['PRUEF Substrat' => 'Fagus', 'PRUEF Privatfeld' => 'geheim']]);
        $sifted = foreign_input_sift($pdo, $pointA, $access['rights'], [
            'title'  => 'PRUEF Punkt A (bearbeitet)',
            'extra' => ['PRUEF Substrat' => 'Quercus', 'PRUEF Privatfeld' => 'übermalt'],
        ]);
        check($sifted['title'] === 'PRUEF Punkt A (bearbeitet)', 'the allowed title goes through');
        check($sifted['extra']['PRUEF Substrat'] === 'Quercus'
              && $sifted['extra']['PRUEF Privatfeld'] === 'geheim',
            'in extra only the released key wins, the other stays stored');
        check(rejected(fn() => foreign_input_sift($pdo, $pointA, ['title'], ['note' => 'x'])),
            'a note without the note right fails loudly');
        check(rejected(fn() => foreign_input_sift($pdo, $pointA, ['title'], ['found_at' => '2026-01-01'])),
            'a field outside the world of rights always fails');

        // Geometry hangs on the move right — marker AND area: whoever
        // may move the spot may drag the corners (14.08.2026).
        check(rejected(fn() => foreign_input_sift($pdo, $pointA, ['title'],
                ['outline' => [[50.1, 8.6], [50.1, 8.61], [50.09, 8.61]]])),
            'an outline without the move right fails loudly');
        // NOT into $sifted — further down that carries the title state the
        // API re-enactment and the anchor tests actually write.
        $geometry = foreign_input_sift($pdo, $pointA, ['move'],
            ['outline' => [[50.1, 8.6], [50.1, 8.61], [50.09, 8.61]]]);
        check(count($geometry['outline']) === 3,
            'with the move right the outline goes through — geometry is geometry');

        // As the API would do it: write in the owner's name, then read.
        point_update($pdo, $anna, $pointA, $sifted);
        $foreign = foreign_point_load($pdo, $ben, $pointA);
        check($foreign['title'] === 'PRUEF Punkt A (bearbeitet)'
              && $foreign['owner'] === 'PRUEF Anna' && $foreign['lists'] === [$leaf],
            'foreign_point_load shows the new state with owner and only the container lists');
        check(!array_key_exists('field_times', $foreign),
            "the owner's field times stay private");

        // Deleting with the right: into Anna's trash, after which the point
        // is gone for members — including for the rights check.
        point_delete($pdo, $anna, $pointB);
        check(point_foreign_rights($pdo, $ben, $pointB) === null,
            'a point in the trash is no longer a point for members');
        $inTrash = (int) $pdo->query(
            "SELECT COUNT(*) FROM map_points WHERE id = $pointB AND deleted_at IS NOT NULL"
        )->fetchColumn();
        check($inTrash === 1, "deleted means: in the owner's trash");

        share_rights_set($pdo, $anna, $fgId, []);
        check(point_foreign_rights($pdo, $ben, $pointA)['rights'] === [],
            'an empty set withdraws everything again — read only');
    } else {
        echo "Rights: migration 0043 missing, skipped.\n";
    }

    if (share_joint_ready($pdo)) {
        echo "Joint lists (gemeinsam bearbeiten, step 1):\n";
        $fgId = (int) $pdo->query(
            "SELECT id FROM map_container_lists WHERE container_id = $cId AND user_id = $anna"
        )->fetchColumn();
        check(rejected(fn() => share_joint_set($pdo, $ben, $fgId, true)),
            'only the sharer switches her list to joint');
        check(!in_array($leaf, joint_list_ids($pdo, $ben), true),
            'before the switch ben may not file into the leaf');
        $before = point_create($pdo, $ben, ['lat' => 51.15, 'lon' => 10.15, 'title' => 'PRUEF Bens Versuch', 'lists' => [$leaf]]);
        check((int) $pdo->query("SELECT COUNT(*) FROM map_point_lists WHERE point_id = $before")->fetchColumn() === 0,
            "a point ben files into anna's leaf before the switch lands in no list");
        point_delete_permanently($pdo, $ben, $before) || $pdo->exec("DELETE FROM map_points WHERE id = $before");

        share_joint_set($pdo, $anna, $fgId, true);
        check(in_array($leaf, joint_list_ids($pdo, $ben), true),
            'switched on, the leaf is a list ben may file into');
        check(in_array($leaf, joint_list_ids($pdo, $carla), true),
            'and carla, member through the group, likewise');
        check(joint_list_ids($pdo, $anna) === [],
            "the sharer's own lists are not listed — they are hers anyway");
        $benPoint = point_create($pdo, $ben, ['lat' => 51.15, 'lon' => 10.15, 'title' => 'PRUEF Bens Punkt', 'lists' => [$leaf]]);
        $lists = array_map('intval', $pdo->query("SELECT list_id FROM map_point_lists WHERE point_id = $benPoint")->fetchAll(PDO::FETCH_COLUMN));
        check($lists === [$leaf], "ben's point lies in anna's leaf");
        $delivered = array_map('intval', array_column(container_points($pdo, $cId), 'id'));
        check(in_array($benPoint, $delivered, true), "the container delivers ben's point");

        $access = point_foreign_rights($pdo, $anna, $benPoint);
        check($access !== null && $access['joint'] === true && $access['owner'] === $ben,
            "anna reaches ben's point in her own list — as a joint one");
        check(in_array('title', $access['rights'], true)
            && in_array('delete_photos', $access['rights'], true)
            && in_array('field:PRUEF Substrat', $access['rights'], true),
            'with every right there is, the field right included');
        $access = point_foreign_rights($pdo, $carla, $benPoint);
        check($access !== null && $access['joint'] === true,
            "carla reaches ben's point the same way");
        $access = point_foreign_rights($pdo, $ben, $pointA);
        check($access !== null && $access['joint'] === true && in_array('note', $access['rights'], true),
            "and anna's own points in the list are joint for the members too, whatever the granted rights say");
        check(container_shares($pdo, $cId)[0]['joint'] === true
            && in_array('move', container_shares($pdo, $cId)[0]['rights'], true),
            'the share reports itself joint, with the full rights');

        require_once __DIR__ . '/../includes/joint_lists.php';
        if (joint_trash_ready($pdo)) {
            echo "Joint lists, step 2 — the list's trash and the chat lines:\n";
            $chatCount = static fn(): int => (int) $pdo->query("SELECT COUNT(*) FROM map_container_messages WHERE container_id = $cId")->fetchColumn();
            $lastChat  = static fn(): string => (string) $pdo->query("SELECT text FROM map_container_messages WHERE container_id = $cId ORDER BY id DESC LIMIT 1")->fetchColumn();
            $c0 = $chatCount();
            // A change by a member: before and after, the list named — and
            // two fields changed at once make ONE line (24.09.2026).
            $before = joint_snapshot($pdo, $pointA);
            point_update($pdo, $anna, $pointA, ['note' => 'PRUEF Notiz', 'title' => 'PRUEF Punkt A (gemeinsam)']);
            joint_change_note($pdo, $ben, $pointA, $before, joint_snapshot($pdo, $pointA));
            check($chatCount() === $c0 + 1, 'title and note changed at once: one chat line');
            check(str_contains($lastChat(), 'Titel „PRUEF Punkt A (bearbeitet)" → „PRUEF Punkt A (gemeinsam)"')
                && str_contains($lastChat(), 'Notiz „PRUEF Notiz" eingetragen') && str_contains($lastChat(), 'in der Liste „PRUEF Blatt"'),
                'it says old and new title, the new note and the list: ' . $lastChat());
            joint_change_note($pdo, $ben, $pointA, joint_snapshot($pdo, $pointA), joint_snapshot($pdo, $pointA));
            check($chatCount() === $c0 + 1, 'nothing changed, nothing said');

            // FOLDING: ben goes on with the same point — the line grows instead of a second one.
            $before = joint_snapshot($pdo, $pointA);
            point_update($pdo, $anna, $pointA, ['note' => 'PRUEF Notiz, ergänzt']);
            joint_change_note($pdo, $ben, $pointA, $before, joint_snapshot($pdo, $pointA));
            check($chatCount() === $c0 + 1 && str_contains($lastChat(), 'Notiz „PRUEF Notiz, ergänzt" eingetragen'),
                'a further change of the same member on the same point folds into the line — the note keeps its first „before": ' . $lastChat());
            $before = joint_snapshot($pdo, $pointA);
            point_update($pdo, $anna, $pointA, ['title' => 'PRUEF Punkt A (bearbeitet)']);
            joint_change_note($pdo, $ben, $pointA, $before, joint_snapshot($pdo, $pointA));
            check($chatCount() === $c0 + 1 && !str_contains($lastChat(), 'Titel') && str_contains($lastChat(), 'Notiz'),
                'a title brought back to where it was drops out of the line: ' . $lastChat());
            // Somebody says something in between: the next change starts a line of its own.
            chat_write($pdo, $carla, $cId, 'PRUEF dazwischen');
            $before = joint_snapshot($pdo, $pointA);
            point_update($pdo, $anna, $pointA, ['note' => 'PRUEF Notiz']);
            joint_change_note($pdo, $ben, $pointA, $before, joint_snapshot($pdo, $pointA));
            check($chatCount() === $c0 + 3, 'a word in between ends the fold — a new line');
            // Another member on the same point: a line of her own.
            $before = joint_snapshot($pdo, $pointA);
            point_update($pdo, $anna, $pointA, ['note' => 'PRUEF Notiz von anna']);
            joint_change_note($pdo, $anna, $pointA, $before, joint_snapshot($pdo, $pointA));
            check($chatCount() === $c0 + 4, 'another member never folds into ben\'s line');
            $replaced = (int) ((json_decode((string) $pdo->query("SELECT meta FROM map_container_messages WHERE container_id = $cId ORDER BY id DESC LIMIT 1")->fetchColumn(), true) ?: [])['replaces'] ?? 0);
            check($replaced === 0, 'a fresh line replaces nothing');
            // A NEW POINT IS ONE LINE (the maintainer's burn site, 24.09.2026: set,
            // titled, noted, moved, outlined — five lines in six minutes).
            $pointC = point_create($pdo, $anna, ['lat' => 51.3, 'lon' => 10.3, 'title' => '', 'lists' => [$leaf]]);
            $c1 = $chatCount();
            joint_created_note($pdo, $anna, $pointC);
            foreach ([['title' => 'PRUEF Brandfläche'], ['note' => 'PRUEF verkohlt'], ['lat' => 51.31, 'lon' => 10.31]] as $step) {
                $before = joint_snapshot($pdo, $pointC);
                point_update($pdo, $anna, $pointC, $step);
                joint_change_note($pdo, $anna, $pointC, $before, joint_snapshot($pdo, $pointC));
            }
            check($chatCount() === $c1 + 1 && $lastChat() === 'hat „PRUEF Brandfläche" neu angelegt, in der Liste „PRUEF Blatt"',
                'creating and filling in a point is one line, under its current name: ' . $lastChat());
            $pdo->exec("DELETE FROM map_points WHERE id = $pointC");
            $kind = (string) $pdo->query("SELECT kind FROM map_container_messages WHERE container_id = $cId ORDER BY id DESC LIMIT 1")->fetchColumn();
            check($kind === 'change', "the line is of kind 'change'");
            // Ben's point out of the list — by anna, a member of the joint share.
            $r = joint_filing_remove($pdo, $anna, $benPoint, [$leaf]);
            check($r['removed'] === [$leaf] && $r['whole'] === true,
                'taking the only filing out trashes the record as a whole');
            check(!in_array($benPoint, array_map('intval', array_column(container_points($pdo, $cId), 'id')), true),
                'the container no longer delivers it');
            check((int) $pdo->query("SELECT COUNT(*) FROM map_list_trash WHERE point_id = $benPoint AND list_id = $leaf AND removed_by = $anna")->fetchColumn() === 1,
                "the list's trash holds the filing with anna's name on it");
            check($pdo->query("SELECT deleted_at FROM map_points WHERE id = $benPoint")->fetchColumn() !== null,
                "ben finds the point in his own trash");
            check(point_in_joint_trash($pdo, $benPoint) && point_delete_permanently($pdo, $ben, $benPoint) === false,
                'and cannot purge it while the list trash holds it');
            check(str_contains($lastChat(), 'Papierkorb der Liste'), 'the chat says so: ' . $lastChat());
            $held = joint_trash_lists($pdo, $carla);
            check(count($held) === 1 && $held[0]['list']['id'] === $leaf && $held[0]['entries'][0]['point_id'] === $benPoint && $held[0]['entries'][0]['whole'] === true,
                'carla sees the entry in the trash page of the list');
            check(rejected(fn() => joint_filing_restore($pdo, $anna + $ben + $carla + 1000, $benPoint, $leaf)),
                'a stranger cannot restore');
            // Back, by carla — the record revives with this one filing.
            check(joint_filing_restore($pdo, $carla, $benPoint, $leaf), 'carla puts it back');
            check($pdo->query("SELECT deleted_at FROM map_points WHERE id = $benPoint")->fetchColumn() === null
                && point_filings($pdo, $benPoint) === [$leaf],
                'the point is alive again, in the leaf');
            check(in_array($benPoint, array_map('intval', array_column(container_points($pdo, $cId), 'id')), true),
                'and the container delivers it again');
            check(joint_trash_lists($pdo, $carla) === [], 'the list trash is empty again');
            check(str_contains($lastChat(), 'zurückgeholt'), 'the chat says so: ' . $lastChat());
            // A request for a list the point is not in is ignored, not refused.
            check(joint_filing_remove($pdo, $anna, $benPoint, [$private])['removed'] === [],
                'a list the point does not lie in is passed over');
            point_update($pdo, $anna, $pointA, ['note' => '', 'title' => 'PRUEF Punkt A (bearbeitet)']);
        } else {
            echo "Joint lists, step 2: migration 0111 missing, skipped.\n";
        }

        echo "Joint lists, step 3 — the handover chain:\n";
        $owner = static fn(int $listId): int => (int) $pdo->query("SELECT user_id FROM map_lists WHERE id = $listId")->fetchColumn();
        $sharer = static fn(): int => (int) $pdo->query("SELECT user_id FROM map_container_lists WHERE id = $fgId")->fetchColumn();
        // Ben's friend row an hour older than the group's — the fixture wrote both in one second.
        $pdo->exec("UPDATE map_container_members SET added_at = DATE_SUB(NOW(), INTERVAL 1 HOUR)
                     WHERE container_id = $cId AND type = 'friend' AND target_id = $ben");
        $senior = container_senior_member($pdo, $cId, [$anna]);
        check($senior === $ben, 'the senior member after anna is ben — a friend of the first hour, before the group came');
        check(container_senior_member($pdo, $cId, [$anna, $ben, $carla]) === null, 'nobody else: no heir');
        $c3 = (int) $pdo->query("SELECT COUNT(*) FROM map_container_messages WHERE container_id = $cId")->fetchColumn();
        $handed = list_delete($pdo, $anna, $branch);
        check(($handed['handed_over']['to'] ?? null) === $ben, 'anna deletes the hung-in branch — it is handed to ben instead');
        check($owner($leaf) === $ben, 'the list is his now');
        check($pdo->query("SELECT parent_id FROM map_lists WHERE id = $leaf")->fetchColumn() === null, 'and it stands at his top');
        check($sharer() === $ben, 'the share row followed — the list stays in the circle');
        check((int) $pdo->query("SELECT user_id FROM map_points WHERE id = $pointA")->fetchColumn() === $anna
            && in_array($leaf, point_filings($pdo, $pointA), true),
            "anna's point stays hers, filed where it was (in the leaf and in her private list, as the fixture set it)");
        $access = point_foreign_rights($pdo, $ben, $pointA);
        check($access !== null && $access['joint'] === true, "ben reaches anna's point in his list now — as a joint one");
        check(point_foreign_rights($pdo, $anna, $benPoint) !== null, "and anna reaches ben's point through the list that was hers");
        check(str_contains((string) $pdo->query("SELECT text FROM map_container_messages WHERE container_id = $cId ORDER BY id DESC LIMIT 1")->fetchColumn(), 'übergeben'),
            'the chat says so');
        // Ben deletes: the next senior member is anna (the container's owner, there from the start).
        $handed = list_delete($pdo, $ben, $branch);
        check(($handed['handed_over']['to'] ?? null) === $anna && $owner($leaf) === $anna && $sharer() === $anna,
            'ben deletes — it goes back to anna, the owner of the container');
        check((int) $pdo->query("SELECT COUNT(*) FROM map_container_messages WHERE container_id = $cId")->fetchColumn() === $c3 + 2, 'two lines for two handovers');
        // A departure: carla's point in the leaf goes with her when she leaves.
        $carlaPoint = point_create($pdo, $carla, ['lat' => 51.16, 'lon' => 10.16, 'title' => 'PRUEF Carlas Punkt', 'lists' => [$leaf]]);
        check(point_filings($pdo, $carlaPoint) === [$leaf], "carla files a point into anna's leaf");
        container_remove($pdo, $anna, $cId, 'group', $group);
        $home = static function (int $pointId, int $who) use ($pdo): ?string {
            $lists = point_filings($pdo, $pointId);
            if (count($lists) !== 1) {
                return null;
            }
            $stmt = $pdo->prepare('SELECT name FROM map_lists WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
            $stmt->execute([$lists[0], $who]);
            return $stmt->fetchColumn() ?: null;
        };
        check($home($carlaPoint, $carla) === 'PRUEF Blatt (ehemals gemeinsam)',
            'taken out of the circle, her point left the joint list with her — into a list of her own, named after the list');
        check($pdo->query("SELECT deleted_at FROM map_points WHERE id = $carlaPoint")->fetchColumn() === null, 'alive, hers, without a list');
        check(str_contains((string) $pdo->query("SELECT text FROM map_container_messages WHERE container_id = $cId ORDER BY id DESC LIMIT 1")->fetchColumn(), 'ausgetreten'),
            'the chat says so');
        container_add($pdo, $anna, $cId, 'group', $group);
        point_delete($pdo, $carla, $carlaPoint);
        point_delete_permanently($pdo, $carla, $carlaPoint);

        // THE SAME LIST JOINT IN TWO SHARES (24.09.2026, with
        // Finn's „Waldbrände"): whoever leaves one of them keeps his points
        // in the list he still tends through the other.
        $c2 = container_create($pdo, $anna, 'PRUEF Zweite Freigabe');
        container_add($pdo, $anna, $c2, 'friend', $carla);
        share_create($pdo, $anna, $c2, $branch);
        $share2 = (int) $pdo->query("SELECT id FROM map_container_lists WHERE container_id = $c2 AND list_id = $branch")->fetchColumn();
        share_joint_set($pdo, $anna, $share2, true);
        $carlaStays = point_create($pdo, $carla, ['lat' => 51.17, 'lon' => 10.17, 'title' => 'PRUEF Carla bleibt', 'lists' => [$leaf]]);
        container_remove($pdo, $anna, $cId, 'group', $group);
        check(point_filings($pdo, $carlaStays) === [$leaf],
            'carla leaves one share — her point stays in the list she still tends jointly through the other');
        container_remove($pdo, $anna, $c2, 'friend', $carla);
        check($home($carlaStays, $carla) === 'PRUEF Blatt (ehemals gemeinsam)',
            'and only when she leaves the last share that holds it jointly does it go home with her');
        container_add($pdo, $anna, $cId, 'group', $group);
        point_delete($pdo, $carla, $carlaStays);
        point_delete_permanently($pdo, $carla, $carlaStays);
        container_delete($pdo, $anna, $c2);

        share_joint_set($pdo, $anna, $fgId, false);
        check(point_foreign_rights($pdo, $anna, $benPoint) === null,
            "switched off, anna no longer reaches ben's point through her list");
        check($home($benPoint, $ben) === 'PRUEF Blatt (ehemals gemeinsam)', "and his point went back to him, out of her list, into „PRUEF Blatt (ehemals gemeinsam)\"");
        check(str_contains((string) $pdo->query("SELECT text FROM map_container_messages WHERE container_id = $cId ORDER BY id DESC LIMIT 1")->fetchColumn(), 'nicht mehr gemeinsam'),
            'the chat says so');
        check(!in_array($leaf, joint_list_ids($pdo, $ben), true),
            'and ben may not file into it any more');
        check(point_foreign_rights($pdo, $ben, $pointA)['rights'] === [],
            'the granted rights are back to what they were');
        // Ben's point goes for good — the sections below count the container's points.
        point_delete($pdo, $ben, $benPoint);
        point_delete_permanently($pdo, $ben, $benPoint);
    } else {
        echo "Joint lists: migration 0110 missing, skipped.\n";
    }

    if (original_ready($pdo)) {
        echo "Rescue anchor (stage 4):\n";
        // Starting position: after the rights block pointA is called "PRUEF
        // Punkt A (bearbeitet)" and carries extra {Substrat: Quercus,
        // Privatfeld: geheim} — for the anchor the state BEFORE the foreign
        // write counts, hence a fresh round with a clean anchor here.
        $pdo->exec("UPDATE map_points SET original = NULL WHERE id = $pointA");

        // ben changes title and Substrat — both anchor with the old value.
        $input = ['title' => 'PRUEF Umbenannt von ben',
                  'extra' => ['PRUEF Substrat' => 'Fagus sylvatica']];
        original_remember($pdo, $pointA, $input, 'pruef-ben');
        point_update($pdo, $anna, $pointA, ['title' => $input['title'],
            'extra' => ['PRUEF Substrat' => 'Fagus sylvatica', 'PRUEF Privatfeld' => 'geheim']]);

        $dev = point_deviations($pdo, $anna);
        check(count($dev) === 2, 'two fields deviate from the original');
        $titleRow = array_values(array_filter($dev, fn($a) => $a['field'] === 'title'))[0] ?? null;
        check($titleRow !== null
              && $titleRow['original'] === 'PRUEF Punkt A (bearbeitet)'
              && $titleRow['now'] === 'PRUEF Umbenannt von ben'
              && $titleRow['by'] === 'pruef-ben',
            'the anchor holds the old title and names who changed it');

        // A SECOND foreign change does not touch the anchor.
        $second = ['title' => 'PRUEF Nochmal umbenannt'];
        original_remember($pdo, $pointA, $second, 'pruef-carla');
        point_update($pdo, $anna, $pointA, $second);
        $dev = point_deviations($pdo, $anna);
        $titleRow = array_values(array_filter($dev, fn($a) => $a['field'] === 'title'))[0];
        check($titleRow['original'] === 'PRUEF Punkt A (bearbeitet)'
              && $titleRow['by'] === 'pruef-ben',
            'the anchor stays at the FIRST foreign write');

        // DRAWN, not only computed: producer and reader once disagreed on
        // two keys, and only the drawing noticed — by breaking the whole
        // administration off (23.09.2026).
        require_once __DIR__ . '/../includes/share_content.php';
        ob_start();
        share_deviations_render($dev, '/test');
        $drawn = (string) ob_get_clean();
        check(str_contains($drawn, '<strong>PRUEF Nochmal umbenannt</strong>')
              && str_contains($drawn, 'geändert von pruef-ben'),
            'the administration draws the deviation with the name of who changed it');

        // Moving anchors the location.
        $loc = ['lat' => 51.15, 'lon' => 10.15];
        original_remember($pdo, $pointA, $loc, 'pruef-ben');
        point_update($pdo, $anna, $pointA, $loc);
        $dev = point_deviations($pdo, $anna);
        $locRow = array_values(array_filter($dev, fn($a) => $a['field'] === 'position'))[0] ?? null;
        check($locRow !== null && abs($locRow['original']['lat'] - 51.1) < 1e-6,
            'the position too anchors with the old location');

        // Restoring: the title comes back, the anchor STAYS.
        original_restore($pdo, $anna, $pointA, 'title');
        $titleNow = $pdo->query("SELECT title FROM map_points WHERE id = $pointA")->fetchColumn();
        check($titleNow === 'PRUEF Punkt A (bearbeitet)',
            'restoring brings the original back');
        $dev = point_deviations($pdo, $anna);
        check(array_filter($dev, fn($a) => $a['field'] === 'title') === [],
            'the deviation has thereby vanished from the list');
        check(isset(original_entry($pdo, $anna, $pointA, 'title')['value']),
            'the anchor itself remains — for the next foreign change');

        // "So lassen": the Substrat anchor falls, the current state is the baseline.
        original_forget($pdo, $anna, $pointA, 'extra.PRUEF Substrat');
        $dev = point_deviations($pdo, $anna);
        check(array_filter($dev, fn($a) => $a['field'] === 'extra.PRUEF Substrat') === [],
            '"So lassen" accepts the foreign change');

        // If the OWNER writes herself, the field's anchor falls: her own
        // state is the original. (This is how the own PATCH path calls it.)
        original_maintain($pdo, $anna, $pointA, ['position' => true, 'lat' => 51.2, 'lon' => 10.2]);
        point_update($pdo, $anna, $pointA, ['lat' => 51.2, 'lon' => 10.2]);
        check(rejected(fn() => original_entry($pdo, $anna, $pointA, 'position')),
            "one's own change releases the anchor — what I enter myself is the original");
        $column = $pdo->query("SELECT original FROM map_points WHERE id = $pointA")->fetchColumn();
        check($column !== null,
            'the title anchor still stands — only the touched field fell');
    } else {
        echo "Rescue anchor: migration 0044 missing, skipped.\n";
    }

    echo "Membership is the boundary:\n";
    group_remove($pdo, $carla, $group, $carla);
    check(!container_visible($pdo, $carla, $cId),
        'whoever leaves the group leaves the container');
    group_add($pdo, $anna, $group, $carla);
    check(container_visible($pdo, $carla, $cId),
        'whoever re-enters is back in — the group counts live');

    // ben hangs a list in and then leaves the container.
    $bList = list_create($pdo, $ben, ['name' => 'PRUEF Bens Liste', 'color' => '#446644', 'symbol' => 'check']);
    share_create($pdo, $ben, $cId, $bList);
    check(count(container_shares($pdo, $cId)) === 2, 'a member too can hang in');
    container_remove($pdo, $ben, $cId, 'friend', $ben);
    check(count(container_shares($pdo, $cId)) === 1,
        "with leaving, one's own share falls");
    check(rejected(fn() => share_create($pdo, $ben, $cId, $bList)),
        'whoever is out hangs nothing in any more');

    if (additions_ready($pdo)) {
        echo "The room grows (migration 0049):\n";
        // State here: anna (owner, share hangs) and carla (via the group)
        // are members; ben has just left — shrinkage is not growth.
        check(additions_per_container($pdo, $anna) === [],
            'no notice as long as the room has not grown');

        // carla hangs something in herself — only that gives her a claim to
        // the notice, and her snapshot comes into being at this moment.
        $cList = list_create($pdo, $carla, ['name' => 'PRUEF Carlas Liste', 'color' => '#664444', 'symbol' => 'cross']);
        share_create($pdo, $carla, $cId, $cList);
        check(additions_per_container($pdo, $carla) === [],
            'whoever hangs in has seen the round at that moment — no alarm about the existing crowd');

        // Two more test accounts for the two ways of growing.
        foreach (['pruef-dora', 'pruef-emil'] as $who) {
            $pdo->prepare(
                "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
                 VALUES (?, '', 'PRUEF', ?, 'user', NOW())"
            )->execute([$who . '@pruefung.local', ucfirst(substr($who, 6))]);
            $accounts[$who] = (int) $pdo->lastInsertId();
            $newId = (int) $accounts[$who];
            friendship_request($pdo, $anna, $newId);
            $fNew = (int) $pdo->query("SELECT id FROM map_friends WHERE from_user_id = $anna AND to_user_id = $newId")->fetchColumn();
            friendship_accept($pdo, $newId, $fNew);
        }
        [$dora, $emil] = [$accounts['pruef-dora'], $accounts['pruef-emil']];

        // Way 1: directly into the container.
        container_add($pdo, $anna, $cId, 'friend', $dora);
        check(additions_per_container($pdo, $anna) === [],
            'the admitter does not get her own action reported');
        $z = additions_per_container($pdo, $carla);
        check(count($z) === 1 && ($z[$cId]['added'] ?? []) === ['PRUEF Dora'],
            'whoever has hung in learns of the direct growth — by name');

        // Way 2: the group in the container grows.
        group_add($pdo, $anna, $group, $emil);
        $z = additions_per_container($pdo, $carla);
        check(($z[$cId]['added'] ?? []) === ['PRUEF Dora', 'PRUEF Emil'],
            'growth of the admitted group counts as growth too');
        check(additions_per_container($pdo, $anna) === [],
            'and that too not for the one who filled the group herself');

        // No claim without an own share — ben is out and empty.
        check(additions_per_container($pdo, $ben) === [],
            'without an own share there is nothing to lose, hence no notice');

        additions_seen($pdo, $carla, $cId);
        check(additions_per_container($pdo, $carla) === [],
            '"Verstanden" clears the notice');

        // Back to the starting state of the following blocks: dora and emil
        // out again, carla's share stays (the end of the friendship below
        // has to tear it down along — exactly that is what it tests).
        container_remove($pdo, $anna, $cId, 'friend', $dora);
        group_remove($pdo, $anna, $group, $emil);
    } else {
        echo "The room grows: migration 0049 missing, skipped.\n";
    }

    if (filter_ready($pdo)) {
        echo "The birth filter stays tight (migration 0050):\n";
        check(point_in_polygon(51.05, 10.05, [[51.0, 10.0], [51.0, 10.15], [51.15, 10.15], [51.15, 10.0]]),
            'the ray test finds the point in the quadrangle');
        check(!point_in_polygon(52.0, 11.0, [[51.0, 10.0], [51.0, 10.15], [51.15, 10.15], [51.15, 10.0]]),
            'and leaves out the one that is out');

        $fFilter = [
            'time'     => ['from' => '2026-06-01', 'until' => '2026-06-30'],
            'area'   => ['name' => 'PRUEF Viereck',
                           'corners' => [[51.0, 10.0], [51.0, 10.15], [51.15, 10.15], [51.15, 10.0]]],
            'terms' => [['type' => 'contains', 'text' => 'pruef'],
                           ['type' => 'contains_not', 'text' => 'geheim']],
        ];
        $cf = container_create($pdo, $anna, 'PRUEF Exkursion', $fFilter);
        check(container_filter($pdo, $cf) === $fFilter,
            'the filter survives the round trip through the database');
        check(container_filter($pdo, $cId) === null,
            'the old container has none — it keeps sharing everything');
        $readable = container_filter_readable($fFilter);
        check(str_contains($readable, '01.06.2026 bis 30.06.2026')
              && str_contains($readable, 'PRUEF Viereck')
              && str_contains($readable, 'enthält nicht „geheim“'),
            'the readable version names time range, area and rules');
        check(str_contains((string) container_filter_readable(
                ['area' => ['corners' => [[51.0, 10.0], [51.0, 10.1], [51.1, 10.1]]]]
            ), 'im umrandeten Gebiet'),
            'a self-drawn area without a name is called after what it is');

        container_add($pdo, $anna, $cf, 'friend', $ben);
        $fList = list_create($pdo, $anna, ['name' => 'PRUEF Filterliste', 'color' => '#557755', 'symbol' => 'circle']);
        share_create($pdo, $anna, $cf, $fList);

        // Six points, one per trap — only the first may come through.
        $cases = [
            ['lat' => 51.05, 'lon' => 10.05, 'title' => 'PRUEF Fund Juni',    'time' => '2026-06-15 12:00:00', 'expected' => true],
            ['lat' => 51.05, 'lon' => 10.05, 'title' => 'PRUEF Fund Juli',    'time' => '2026-07-15 12:00:00', 'expected' => false],
            ['lat' => 52.00, 'lon' => 11.00, 'title' => 'PRUEF Fund abseits', 'time' => '2026-06-10 12:00:00', 'expected' => false],
            ['lat' => 51.06, 'lon' => 10.04, 'title' => 'PRUEF Geheimfund',   'time' => '2026-06-12 12:00:00', 'expected' => false],
            ['lat' => 51.07, 'lon' => 10.03, 'title' => 'Fund ohne Kuerzel',  'time' => '2026-06-13 12:00:00', 'expected' => false],
            ['lat' => 51.08, 'lon' => 10.02, 'title' => 'PRUEF ohne Zeit',    'time' => null,                  'expected' => false],
        ];
        $fPoints = [];
        foreach ($cases as $i => $case) {
            $id = point_create($pdo, $anna, [
                'lat' => $case['lat'], 'lon' => $case['lon'],
                'title' => $case['title'], 'lists' => [$fList],
            ]);
            $pdo->prepare('UPDATE map_points SET found_at = ? WHERE id = ?')
                ->execute([$case['time'], $id]);
            $fPoints[$i] = $id;
        }

        $delivered = array_map('intval', array_column(container_points($pdo, $cf), 'id'));
        check($delivered === [$fPoints[0]],
            'of six points exactly the one comes through that passes time, area and both rules');
        check(container_filtered_count($pdo, [$fList], $fFilter) === 1,
            'the honest number on the share row counts the same');

        check(point_foreign_rights($pdo, $ben, $fPoints[0]) !== null,
            'the matching point the member may also see individually');
        foreach ([1 => 'time', 2 => 'area', 3 => 'contains-not', 4 => 'contains', 5 => 'missing find time'] as $i => $reason) {
            check(point_foreign_rights($pdo, $ben, $fPoints[$i]) === null,
                "single access stays tight where the delivery stays tight ($reason)");
        }

        container_delete($pdo, $anna, $cf);
    } else {
        echo "Birth filter: migration 0050 missing, skipped.\n";
    }

    // End of the friendship: carla was friend AND group member — both end.
    $fId = (int) $pdo->query("SELECT id FROM map_friends WHERE from_user_id = $anna AND to_user_id = $carla")->fetchColumn();
    friendship_remove($pdo, $carla, $fId);
    check(!container_visible($pdo, $carla, $cId),
        'with the friendship the container view via the group ends too');

    echo "Deleting:\n";
    list_delete($pdo, $anna, $branch);
    check(container_shares($pdo, $cId) === [],
        'if the shared branch is deleted, its share falls');
    check(rejected(fn() => container_delete($pdo, $ben, $cId)),
        "someone else's container cannot be deleted");
    container_delete($pdo, $anna, $cId);
    check(container_mine($pdo, $anna) === [], 'the own container is deleted');
} finally {
    $ids = implode(',', $accounts);
    if (table_exists($pdo, 'map_container_state')) {
        $pdo->exec("DELETE FROM map_container_state WHERE user_id IN ($ids)
                     OR container_id IN (SELECT id FROM map_containers WHERE owner_user_id IN ($ids))");
    }
    $pdo->exec("DELETE FROM map_container_lists WHERE user_id IN ($ids)");
    $pdo->exec("DELETE FROM map_container_members WHERE container_id IN
                (SELECT id FROM map_containers WHERE owner_user_id IN ($ids))");
    $pdo->exec("DELETE FROM map_containers WHERE owner_user_id IN ($ids)");
    $pdo->exec("DELETE FROM map_friends WHERE from_user_id IN ($ids) OR to_user_id IN ($ids)");
    $pdo->exec("DELETE FROM map_group_members WHERE user_id IN ($ids)
                 OR group_id IN (SELECT id FROM map_groups WHERE owner_user_id IN ($ids))");
    $pdo->exec("DELETE FROM map_groups WHERE owner_user_id IN ($ids)");
    // Points, lists, fields go with the account via ON DELETE CASCADE.
    $pdo->exec("DELETE FROM map_users WHERE id IN ($ids)");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
