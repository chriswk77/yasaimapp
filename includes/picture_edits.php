<?php
/**
 * FOREIGN EDITS OF A PICTURE (11.11.2026, migration 0194).
 *
 * A friend who may see a picture may lay HIS OWN edits beside the
 * photographer's — when the photographer allows it. Shown in the photo
 * window's bar „Bearbeitung" with the maker's name; only the photographer
 * makes one of them his valid edit, and then it is copied into his own and
 * is his (the client does that through the photographer's usual save).
 *
 * TWO WAYS TO ALLOW, both his („Zu 1, ja, beide"):
 *   the recipient's tick `edit_photos` in a list share („Bilder
 *     bearbeiten") — for every picture of a find in that list whose
 *     photographer is the list's sharer: he shares HIS list, and a picture
 *     on one of his finds that a co-owner took is that co-owner's, not his
 *     to give („ein Bild gehört dem, der es aufgenommen hat");
 *   the picture's own switch `edit_open` („Andere dürfen bearbeiten") — for
 *     everyone who may see the picture at all.
 *
 * WHO SEES WHAT (his „wenn wir ein Bild freigeben für alle zum Bearbeiten,
 * dann darf jeder auch die bearbeiteten Bilder der anderen sehen", made
 * one rule): the photographer and everyone who may edit the picture see
 * every edit on it. Whoever loses the right sees none of them any more —
 * his own neither —, and nothing is deleted for it: it is his work.
 *
 * ONLY THE DATABASE (the same evening: „nur Datenbank"). Never
 * in the photographer's `develop` or `develop_versions`, never in the
 * sidecar in his cloud.
 *
 * „ENTFERNEN" IN A FOREIGN EDIT (stage 4, 12.11.2026): the model runs on
 * the EDITOR's device, but its patches go into the PHOTOGRAPHER's cloud,
 * into the same „Bearbeitung" beside the picture as his own („ein Foto,
 * eine Bearbeitung, eine Cloud. Alles beim Besitzer"). The editor's cloud
 * is never asked — he need not have one. They ride with the save of the
 * edit that names them and are written BEFORE it, as at the photographer's
 * own save (api/v1/photos.php); a patch nothing names any more goes, but
 * only when no edit of the picture — his, the photographer's, anybody
 * else's — still names it (develop_foreign_patch_ids): the photographer
 * who made a friend's edit valid holds a COPY of its settings, and with
 * them the same patch ids.
 */

declare(strict_types=1);

require_once __DIR__ . '/container.php';
require_once __DIR__ . '/point_owners.php';
require_once __DIR__ . '/photos.php';
require_once __DIR__ . '/develop_patches.php';

/** Edits one person may keep on one picture — as many as the photographer's own (PHOTO_DEVELOP_VERSIONS_MAX). */
const PICTURE_EDITS_PER_EDITOR = 9;

function picture_edits_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_picture_edits');
}

/** The picture's table by its kind, or null for anything else. */
function picture_edit_table(string $kind): ?string
{
    return match ($kind) {
        'photo' => 'map_photos',
        'cloud' => 'map_cloud_files',
        default => null,
    };
}

/**
 * What $userId may do with this picture, or null where nothing: it is not
 * there, or he may not see it, or he may see it but not edit it and is
 * not its photographer.
 *
 *   role 'owner'   the photographer — his own edits as always; he sees the
 *                  others' and switches the picture open or shut
 *   role 'editor'  somebody else who may add edits of his own
 *
 * @return ?array{role: string, owner: int, point: int, open: bool}
 */
function picture_edit_scope(PDO $pdo, int $userId, string $kind, int $id): ?array
{
    $table = picture_edit_table($kind);
    if ($table === null || $id <= 0) {
        return null;
    }
    $open = column_exists($pdo, $table, 'edit_open');
    $stmt = $pdo->prepare('SELECT user_id, point_id' . ($open ? ', edit_open' : ', 0 AS edit_open') . ' FROM ' . $table . ' WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row === false || $row['point_id'] === null) {
        return null;
    }
    $owner = (int) $row['user_id'];
    $pointId = (int) $row['point_id'];
    $isOpen = (int) $row['edit_open'] === 1;
    if ($owner === $userId) {
        return ['role' => 'owner', 'owner' => $owner, 'point' => $pointId, 'open' => $isOpen];
    }
    // Who may see the picture at all: the find's holder, a co-owner of it, a recipient of a share that reaches it.
    $stmt = $pdo->prepare('SELECT user_id FROM map_points WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$pointId]);
    $holder = $stmt->fetchColumn();
    if ($holder === false) {
        return null;
    }
    $foreign = (int) $holder === $userId ? null : point_foreign_rights($pdo, $userId, $pointId);
    $sees = (int) $holder === $userId || point_owner_is($pdo, $pointId, $userId) || $foreign !== null;
    if (!$sees) {
        return null;
    }
    // The tick counts only where the sharer gives his own picture.
    $ticked = $foreign !== null && in_array('edit_photos', $foreign['rights'], true) && $foreign['owner'] === $owner;
    if (!$isOpen && !$ticked) {
        return null;
    }
    return ['role' => 'editor', 'owner' => $owner, 'point' => $pointId, 'open' => $isOpen];
}

/** A foreign edit's settings as they are kept: the photographer's shape (photo_develop_clean). */
function picture_edit_clean(mixed $develop): ?array
{
    $d = photo_develop_clean($develop);
    return $d === [] ? null : $d;
}

/**
 * The picture's row and the cloud it lies in, opened in its PHOTOGRAPHER's
 * name — where an editor's patches go. Throws where there is no cloud to
 * put them in: a place without its patch would remove nothing.
 *
 * @return array{0: array, 1: array, 2: string} row, storage, the picture's id at its provider
 * @throws InvalidArgumentException|RuntimeException
 */
function picture_edit_patch_cloud(PDO $pdo, string $kind, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM ' . picture_edit_table($kind) . ' WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $fileid = $row === false ? '' : develop_patches_fileid($kind, $row);
    if ($fileid === '') {
        throw new InvalidArgumentException(t('Bearbeiten geht nur bei Bildern, die in einer Cloud liegen.'));
    }
    $s = develop_patches_cloud($pdo, $kind, $row);
    if ($s === null) {
        throw new RuntimeException(t('Die Cloud des Bildes ist gerade nicht erreichbar.'));
    }
    return [$row, $s, $fileid];
}

/**
 * After an edit changed or went: the patches it named before and names no
 * more go from the photographer's cloud — unless another edit of the
 * picture still names them. Best effort, like develop_patches_gone.
 *
 * @param list<string> $before
 * @param list<string> $after
 */
function picture_edit_patches_tidy(PDO $pdo, string $kind, int $id, array $before, array $after): void
{
    $gone = array_diff($before, $after);
    if ($gone === []) {
        return;
    }
    try {
        [$row, $s, $fileid] = picture_edit_patch_cloud($pdo, $kind, $id);
        $gone = array_values(array_diff($gone, develop_row_patch_ids($row), develop_foreign_patch_ids($pdo, $kind, $id)));
        develop_patches_gone($s, $fileid, $gone);
    } catch (InvalidArgumentException) {
        // No cloud under the picture: no patch can lie anywhere.
    } catch (Throwable $e) {
        error_log('picture_edit_patches_tidy (' . $kind . ' ' . $id . '): ' . $e->getMessage());
    }
}

/**
 * Every foreign edit on the picture, oldest first, each with its maker's
 * name — for whoever picture_edit_scope lets in.
 *
 * @return list<array{id: int, user_id: int, name: string, develop: ?array, updated_at: string}>
 */
function picture_edits_of(PDO $pdo, string $kind, int $id): array
{
    if (!picture_edits_ready($pdo) || picture_edit_table($kind) === null) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT e.id, e.user_id, e.develop, e.updated_at, ' . sql_display_name() . ' AS name
           FROM map_picture_edits e JOIN map_users u ON u.id = e.user_id
          WHERE e.picture_kind = ? AND e.picture_id = ?
          ORDER BY e.id'
    );
    $stmt->execute([$kind, $id]);
    return array_map(static fn(array $r): array => [
        'id'         => (int) $r['id'],
        'user_id'    => (int) $r['user_id'],
        'name'       => (string) $r['name'],
        'develop'    => photo_develop_read($r['develop']),
        'updated_at' => (string) $r['updated_at'],
    ], $stmt->fetchAll());
}

/**
 * Keep an editor's edit — a new one (no $editId) or one of his own. Never
 * the photographer's: his edits stay where they are (photos.php,
 * cloud.php). `$patches`: the AI's patches the edit names that the cloud
 * does not hold yet, { id: base64 PNG } — into the photographer's cloud
 * before the settings are kept.
 *
 * @throws InvalidArgumentException  when he may not, or keeps too many
 * @throws RuntimeException          when the photographer's cloud would not take the patches
 * @return int the edit's id
 */
function picture_edit_save(PDO $pdo, int $userId, string $kind, int $id, ?int $editId, mixed $develop, mixed $patches = null): int
{
    $scope = picture_edit_scope($pdo, $userId, $kind, $id);
    if ($scope === null || $scope['role'] !== 'editor' || !picture_edits_ready($pdo)) {
        throw new InvalidArgumentException(t('Dieses Bild darfst du nicht bearbeiten.'));
    }
    $d = picture_edit_clean($develop);
    $json = $d === null ? null : json_encode($d);
    // What the edit named before — to let go of afterwards what it names no more.
    $before = [];
    if ($editId !== null) {
        $stmt = $pdo->prepare('SELECT develop FROM map_picture_edits WHERE id = ? AND user_id = ? AND picture_kind = ? AND picture_id = ?');
        $stmt->execute([$editId, $userId, $kind, $id]);
        $before = develop_patch_ids(photo_develop_read($stmt->fetchColumn() ?: null));
    }
    // The patches FIRST, into the photographer's cloud: a kept edit never names a patch that is not there.
    $bytes = $patches === null ? [] : develop_patches_decode($patches, $d);
    if ($bytes !== []) {
        [, $s, $fileid] = picture_edit_patch_cloud($pdo, $kind, $id);
        develop_patches_store($s, $fileid, $bytes);
    }
    if ($editId !== null) {
        $stmt = $pdo->prepare(
            'UPDATE map_picture_edits SET develop = ?, updated_at = NOW()
              WHERE id = ? AND user_id = ? AND picture_kind = ? AND picture_id = ?'
        );
        $stmt->execute([$json, $editId, $userId, $kind, $id]);
        $stmt = $pdo->prepare('SELECT 1 FROM map_picture_edits WHERE id = ? AND user_id = ? AND picture_kind = ? AND picture_id = ?');
        $stmt->execute([$editId, $userId, $kind, $id]);
        if ($stmt->fetchColumn() === false) {
            throw new InvalidArgumentException(t('Diese Bearbeitung gibt es nicht mehr.'));
        }
        picture_edit_patches_tidy($pdo, $kind, $id, $before, develop_patch_ids($d));
        return $editId;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM map_picture_edits WHERE user_id = ? AND picture_kind = ? AND picture_id = ?');
    $stmt->execute([$userId, $kind, $id]);
    if ((int) $stmt->fetchColumn() >= PICTURE_EDITS_PER_EDITOR) {
        throw new InvalidArgumentException(t('Mehr als %d Bearbeitungen je Bild gehen nicht.', PICTURE_EDITS_PER_EDITOR));
    }
    $pdo->prepare(
        'INSERT INTO map_picture_edits (picture_kind, picture_id, user_id, develop, created_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())'
    )->execute([$kind, $id, $userId, $json]);
    $newId = (int) $pdo->lastInsertId();
    /* The edit is the work, the chat line only news of it: a line that
       fails to be written must not cost the edit that was just kept. */
    try {
        picture_edit_announce($pdo, $userId, $scope['owner'], $scope['point'], $kind, $id);
    } catch (Throwable $e) {
        error_log('picture_edit_announce: ' . $e->getMessage());
    }
    return $newId;
}

/** How long one editor's line about one picture covers his further new edits on it. */
const PICTURE_EDIT_ANNOUNCE_HOURS = 12;

/**
 * THE PHOTOGRAPHER HEARS WHEN SOMEBODY ADDS AN EDIT TO HIS PICTURE
 * (11.11.2026: „wäre cool"; stage 3). Written when an edit is
 * BEGUN, not on every save: the window keeps an edit 700 ms after each
 * slider move, and a line per move would bury the chat. And once per
 * editor and picture in PICTURE_EDIT_ANNOUNCE_HOURS — whoever tries three
 * variants in an evening has done one thing, not three.
 *
 * Where to, as point_hint_tell_holder does it: the chat the two share as
 * friends; where they are none — the open switch reaches whoever sees the
 * find, a working group's member as well — the chat of a share both belong
 * to that carries the find, there worded for everybody who reads along.
 * Where neither exists there is no room for the line; the photographer
 * still finds the edit under his picture.
 */
function picture_edit_announce(PDO $pdo, int $editor, int $owner, int $pointId, string $kind, int $id): void
{
    require_once __DIR__ . '/chat.php';
    $table = picture_edit_table($kind);
    if ($table === null || $editor === $owner) {
        return;
    }
    // The picture by its title, else by its file's name — what the photographer sees under it.
    $stmt = $pdo->prepare(
        'SELECT COALESCE(NULLIF(x.title, \'\'), NULLIF(x.original_name, \'\'), \'\') AS picture,
                p.title AS find, ' . sql_display_name() . ' AS owner
           FROM ' . $table . ' x JOIN map_points p ON p.id = x.point_id JOIN map_users u ON u.id = x.user_id
          WHERE x.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return;
    }
    $picture = (string) $row['picture'] !== '' ? (string) $row['picture'] : t('ohne Namen');
    $find    = (string) $row['find'] !== '' ? (string) $row['find'] : t('Ohne Titel');
    $meta    = ['picture_edit' => $kind . ':' . $id];
    // The mark the line carries, to find it again below — the meta is stored as this JSON.
    $mark = '%' . substr(json_encode($meta, JSON_UNESCAPED_UNICODE), 1, -1) . '%';

    $room = friend_chat_kind_ready($pdo) ? friend_chat_room($pdo, $editor, $owner) : null;
    if ($room !== null) {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM map_friend_messages
              WHERE friendship_id = ? AND user_id = ? AND meta LIKE ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL ' . PICTURE_EDIT_ANNOUNCE_HOURS . ' HOUR) LIMIT 1'
        );
        $stmt->execute([$room, $editor, $mark]);
        if ($stmt->fetchColumn() === false) {
            friend_chat_note($pdo, $editor, $owner,
                t('hat eine eigene Bearbeitung von deinem Bild „%s" beim Fund „%s" angelegt. Du findest sie im Bildfenster unter „Bearbeiten". Deine Bearbeitungen bleiben, wie sie sind.',
                    $picture, $find),
                $meta);
        }
        return;
    }

    // No friends: a share both belong to in which the find's list hangs.
    $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ?');
    $stmt->execute([$pointId]);
    $lists = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if ($lists === [] || !container_ready($pdo) || !chat_meta_ready($pdo)) {
        return;
    }
    foreach (container_mine($pdo, $editor) as $c) {
        if (!in_array($owner, $c['member_ids'], true)
            || array_intersect($lists, array_map('intval', container_list_ids($pdo, $c['id']))) === []) {
            continue;
        }
        $stmt = $pdo->prepare(
            'SELECT 1 FROM map_container_messages
              WHERE container_id = ? AND user_id = ? AND meta LIKE ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL ' . PICTURE_EDIT_ANNOUNCE_HOURS . ' HOUR) LIMIT 1'
        );
        $stmt->execute([$c['id'], $editor, $mark]);
        if ($stmt->fetchColumn() === false) {
            chat_note($pdo, $editor, $c['id'],
                t('hat eine eigene Bearbeitung zum Bild „%s" beim Fund „%s" angelegt. Die Bearbeitungen von %s bleiben, wie sie sind.',
                    $picture, $find, (string) $row['owner']),
                $meta);
        }
        return;
    }
}

/** An editor takes one of his edits away — only his own, its patches with it; true when there was one. */
function picture_edit_delete(PDO $pdo, int $userId, int $editId): bool
{
    if (!picture_edits_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT picture_kind, picture_id, develop FROM map_picture_edits WHERE id = ? AND user_id = ?');
    $stmt->execute([$editId, $userId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return false;
    }
    $pdo->prepare('DELETE FROM map_picture_edits WHERE id = ? AND user_id = ?')->execute([$editId, $userId]);
    picture_edit_patches_tidy($pdo, (string) $row['picture_kind'], (int) $row['picture_id'],
        develop_patch_ids(photo_develop_read($row['develop'])), []);
    return true;
}

/**
 * The picture's switch „Andere dürfen bearbeiten" — the photographer's
 * alone; false where he is not, or before migration 0194.
 */
function picture_edit_open_set(PDO $pdo, int $userId, string $kind, int $id, bool $open): bool
{
    $table = picture_edit_table($kind);
    if ($table === null || !column_exists($pdo, $table, 'edit_open')) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE ' . $table . ' SET edit_open = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$open ? 1 : 0, $id, $userId]);
    $stmt = $pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    return $stmt->fetchColumn() !== false;
}

/**
 * The picture is gone: its foreign edits go with it — there is nothing left
 * they could be laid on. Their patches went with the picture already: they
 * are named after it (storage_sidecars_of).
 */
function picture_edits_forget(PDO $pdo, string $kind, int $id): void
{
    if (picture_edits_ready($pdo) && picture_edit_table($kind) !== null) {
        $pdo->prepare('DELETE FROM map_picture_edits WHERE picture_kind = ? AND picture_id = ?')->execute([$kind, $id]);
    }
}
