<?php
/**
 * Tests the foreign edits of a picture (includes/picture_edits.php,
 * migration 0194).
 *
 *   php tests/picture_edits.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   Who may. The two ways to allow — the picture's switch for everyone
 *   who sees it, the list tick for the sharer's own pictures — and the
 *   ones who may NOT: a stranger, a recipient without either, and a
 *   recipient with the tick on a picture somebody else took on the
 *   sharer's find (a picture is its photographer's to give).
 *
 *   What is kept. An edit is its maker's: only he changes or removes it,
 *   the photographer cannot write into it, nine per person at most. An AI
 *   place of „Entfernen" stays, and its patch counts among the picture's
 *   wherever patches are asked for — but there must be a cloud to put it in.
 *
 *   Who hears. The photographer gets one chat line when somebody begins
 *   an edit of his picture — not one per save, not one per further edit
 *   within hours.
 *
 * Against the demodata fixture (php admin/demodata.php --new): Ben shares
 * a list with Anna, Carla sees nothing of it. Pictures are made for the
 * test and removed again, the share's rights put back as they were.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/picture_edits.php';
require_once __DIR__ . '/../includes/chat.php';
require_once __DIR__ . '/../includes/develop_patches.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo ($b ? '  ok   ' : '  FAIL ') . $what . "\n";
    if (!$b) {
        $failed++;
    }
}

if (!picture_edits_ready($pdo) || !column_exists($pdo, 'map_photos', 'edit_open')) {
    echo "Migration 0194 is not applied — nothing to test.\n";
    exit(1);
}

$id = static fn(string $mail): int => (int) $pdo->query('SELECT id FROM map_users WHERE email = ' . $pdo->quote($mail))->fetchColumn();
[$anna, $ben, $carla] = [$id('anna@demo.local'), $id('ben@demo.local'), $id('carla@demo.local')];
// Ben's list shared with Anna, and one of his finds in it.
$share = $pdo->prepare(
    "SELECT c.id AS container, c.list_id FROM map_containers c
       JOIN map_container_members m ON m.container_id = c.id AND m.type = 'friend' AND m.target_id = ?
      WHERE c.owner_user_id = ? AND c.list_id IS NOT NULL LIMIT 1"
);
$share->execute([$anna, $ben]);
$share = $share->fetch();
$point = $share === false ? false : $pdo->query(
    'SELECT p.id FROM map_points p JOIN map_point_lists pl ON pl.point_id = p.id
      WHERE p.user_id = ' . $ben . ' AND p.deleted_at IS NULL AND pl.list_id = ' . (int) $share['list_id'] . ' LIMIT 1'
)->fetchColumn();
if (!$anna || !$ben || !$carla || $share === false || $point === false) {
    echo "The demodata fixture is missing (php admin/demodata.php --new).\n";
    exit(1);
}
$point = (int) $point;
$container = (int) $share['container'];
$rightsBefore = $pdo->query("SELECT rights FROM map_container_members WHERE container_id = $container AND type = 'friend' AND target_id = $anna")->fetchColumn();
$setRights = static function (?array $rights) use ($pdo, $container, $anna): void {
    $pdo->prepare("UPDATE map_container_members SET rights = ? WHERE container_id = ? AND type = 'friend' AND target_id = ?")
        ->execute([$rights === null ? null : json_encode($rights), $container, $anna]);
};
$photo = static function (int $user) use ($pdo, $point): int {
    $pdo->prepare('INSERT INTO map_photos (user_id, point_id, filename, original_name, width, height, bytes, created_at)
                   VALUES (?, ?, "probe.jpg", "probe.jpg", 800, 600, 4321, NOW())')->execute([$user, $point]);
    return (int) $pdo->lastInsertId();
};
$role = static fn(int $user, int $pic): ?string => picture_edit_scope($pdo, $user, 'photo', $pic)['role'] ?? null;

$made = [];
try {
    $setRights(['title']);
    $byBen = $made[] = $photo($ben);
    $byCarla = $made[] = $photo($carla);

    echo "Who may\n";
    check($role($ben, $byBen) === 'owner', 'the photographer is the owner of his picture');
    check($role($anna, $byBen) === null, 'a recipient without the tick and with the picture shut may not');
    check($role($carla, $byBen) === null, 'a stranger may not');
    picture_edit_open_set($pdo, $ben, 'photo', $byBen, true);
    check($role($anna, $byBen) === 'editor' && $role($carla, $byBen) === null,
        'the switch opens it for whoever sees the picture — not for whoever does not');
    check(!picture_edit_open_set($pdo, $anna, 'photo', $byBen, false) && $role($anna, $byBen) === 'editor',
        'only the photographer turns the switch');
    picture_edit_open_set($pdo, $ben, 'photo', $byBen, false);
    $setRights(['title', 'edit_photos']);
    check($role($anna, $byBen) === 'editor', 'the list tick „Bilder bearbeiten" opens the sharer\'s pictures');
    check($role($anna, $byCarla) === null, 'the tick does not reach a picture somebody else took on the sharer\'s find');
    check($role($ben, $byCarla) === null, 'the find\'s holder may not edit a co-owner\'s picture unasked');
    picture_edit_open_set($pdo, $carla, 'photo', $byCarla, true);
    check($role($ben, $byCarla) === 'editor' && $role($anna, $byCarla) === 'editor',
        'opened by its photographer, it is open to everyone who sees the find');

    echo "What is kept\n";
    $first = picture_edit_save($pdo, $anna, 'photo', $byBen, null, ['exposure' => 30, 'spots' => [
        ['mode' => 'heal', 'path' => [[.5, .5]], 'dx' => .1],
        ['mode' => 'ai', 'path' => [[.2, .2]], 'pieces' => [['id' => str_repeat('a1', 8), 'box' => [0, 0, .5, .5]]]],
    ]]);
    $edits = picture_edits_of($pdo, 'photo', $byBen);
    check(count($edits) === 1 && $edits[0]['user_id'] === $anna && $edits[0]['name'] !== ''
        && $edits[0]['develop']['exposure'] === 30.0 && count($edits[0]['develop']['spots']) === 2
        && $edits[0]['develop']['spots'][1]['mode'] === 'ai',
        'an edit is kept with its maker\'s name, its AI place with it (stage 4)');
    $row = $pdo->query("SELECT * FROM map_photos WHERE id = $byBen")->fetch();
    check(develop_foreign_patch_ids($pdo, 'photo', $byBen) === [str_repeat('a1', 8)]
        && develop_foreign_patch_ids($pdo, 'photo', $byBen, $first) === []
        && in_array(str_repeat('a1', 8), develop_picture_patch_ids($pdo, 'photo', $row), true),
        'her patch counts among the picture\'s — for the reader, the tidying and a cloud move');
    $png = static function (): string {
        $im = imagecreatetruecolor(512, 512);
        ob_start();
        imagepng($im);
        return base64_encode((string) ob_get_clean());
    };
    $kept = picture_edits_of($pdo, 'photo', $byBen)[0]['develop'];
    $threw = false;
    try {
        picture_edit_save($pdo, $anna, 'photo', $byBen, $first, ['exposure' => 1, 'spots' => [
            ['mode' => 'ai', 'path' => [[.3, .3]], 'pieces' => [['id' => str_repeat('b2', 8), 'box' => [0, 0, .5, .5]]]],
        ]], [str_repeat('b2', 8) => $png()]);
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    check($threw && picture_edits_of($pdo, 'photo', $byBen)[0]['develop'] === $kept,
        'a patch for a picture in no cloud is refused, and the edit stays as it was');
    picture_edit_save($pdo, $anna, 'photo', $byBen, $first, ['exposure' => -10]);
    check(picture_edits_of($pdo, 'photo', $byBen)[0]['develop'] === ['exposure' => -10.0], 'he changes his own edit');
    $refused = static function (callable $f): bool {
        try {
            $f();
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    };
    check($refused(static fn() => picture_edit_save($pdo, $ben, 'photo', $byBen, $first, ['exposure' => 50]))
        && $refused(static fn() => picture_edit_save($pdo, $carla, 'photo', $byBen, null, ['exposure' => 50])),
        'neither the photographer nor a stranger writes into it');
    for ($k = 1; $k < PICTURE_EDITS_PER_EDITOR; $k++) {
        picture_edit_save($pdo, $anna, 'photo', $byBen, null, ['exposure' => $k]);
    }
    check($refused(static fn() => picture_edit_save($pdo, $anna, 'photo', $byBen, null, ['exposure' => 99]))
        && count(picture_edits_of($pdo, 'photo', $byBen)) === PICTURE_EDITS_PER_EDITOR,
        'nine edits per person and picture, no more');
    check(!picture_edit_delete($pdo, $ben, $first) && picture_edit_delete($pdo, $anna, $first),
        'only its maker takes an edit away');

    echo "The photographer hears of it\n";
    $mark = '%"picture_edit":"photo:' . $byBen . '"%';
    $lines = static function () use ($pdo, $anna, $mark): int {
        $s = $pdo->prepare('SELECT COUNT(*) FROM map_friend_messages WHERE user_id = ? AND meta LIKE ?');
        $s->execute([$anna, $mark]);
        $n = (int) $s->fetchColumn();
        $s = $pdo->prepare('SELECT COUNT(*) FROM map_container_messages WHERE user_id = ? AND meta LIKE ?');
        $s->execute([$anna, $mark]);
        return $n + (int) $s->fetchColumn();
    };
    check($lines() === 1, 'one line for him when she began, none for the eight that followed within hours, none for her saves');
    $carlaLines = $pdo->prepare('SELECT COUNT(*) FROM map_friend_messages WHERE meta LIKE ?');
    $carlaLines->execute(['%"picture_edit":"photo:' . $byCarla . '"%']);
    $before = (int) $carlaLines->fetchColumn();
    picture_edit_save($pdo, $ben, 'photo', $byCarla, null, ['exposure' => 5]);
    $carlaLines->execute(['%"picture_edit":"photo:' . $byCarla . '"%']);
    $friends = friend_chat_room($pdo, $ben, $carla) !== null;
    check((int) $carlaLines->fetchColumn() === $before + ($friends ? 1 : 0),
        $friends ? 'Carla hears in her chat with Ben' : 'Ben and Carla are no friends: no friend chat line for her');
    $setRights(['title']);
    check($role($anna, $byBen) === null && count(picture_edits_of($pdo, 'photo', $byBen)) === PICTURE_EDITS_PER_EDITOR - 1,
        'the tick taken away: she may not any more — and nothing of her work is deleted');
    picture_edits_forget($pdo, 'photo', $byBen);
    check(picture_edits_of($pdo, 'photo', $byBen) === [], 'the picture gone, its edits go with it');
} finally {
    foreach ($made as $p) {
        // The chat lines the test made the photographers hear.
        foreach (['map_friend_messages', 'map_container_messages'] as $t) {
            $pdo->prepare("DELETE FROM $t WHERE meta LIKE ?")->execute(['%"picture_edit":"photo:' . $p . '"%']);
        }
        $pdo->exec("DELETE FROM map_picture_edits WHERE picture_kind = 'photo' AND picture_id = $p");
        $pdo->exec("DELETE FROM map_photos WHERE id = $p");
    }
    $pdo->prepare("UPDATE map_container_members SET rights = ? WHERE container_id = ? AND type = 'friend' AND target_id = ?")
        ->execute([$rightsBefore === false ? null : $rightsBefore, $container, $anna]);
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
