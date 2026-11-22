<?php
/**
 * A PLATE AS A PICTURE IN THE MAKER'S CLOUD (13.10.2026, migration 0169).
 *
 * „die Tafel als Bild in die Cloud … damit man da schnell eine
 * Vorschau hat und nicht die Tafel jedes Mal neu bauen muss". A plate is a
 * layout that names pictures (plates.php); drawn, it needs every picture
 * loaded, the originals for a row of spores — seconds, each time it is
 * shown. So each page, once drawn, is kept as a JPEG.
 *
 * THE BROWSER DRAWS, THE SERVER FILES. The drawing exists once, in
 * plateprint.js — the export's own — and a second one in PHP would drift
 * from it on the first change. The browser sends the page with a hash of
 * what it was drawn from (plates.js pageHash: the page, the pictures'
 * measures and scales, the details); an unchanged page is never sent
 * twice. The server lays it into the find's folder of the maker's cloud,
 * where his pictures of the find lie.
 *
 * THE NAME STANDS ON TOP („dass sie immer ganz oben steht über
 * den einzelnen Fundbildern"): the find's head as the pictures carry it
 * (cloud_name_head), then „00 Tafel" — the pictures count from „01", and
 * „00 " sorts before „01" and before „001" alike. A plate of several pages
 * says „00 Tafel 1", „00 Tafel 2".
 *
 * NO CLOUD, NO PICTURE — the rule of every picture here (CLAUDE.md, „No
 * cloud, no copy"): the page itself never lies on this server. What does
 * is a small preview, as the approved preview cache keeps one per picture
 * (thumbs.php): the row's tile in „Belege" shows it without a round trip
 * to the cloud. The large one comes from the cloud (pages/plate_image.php).
 *
 * WHERE IT FALLS BEHIND, and why that is acceptable: a find renamed or
 * refiled moves its pictures (cloud_names.php) but not the plate's page —
 * the page follows the next time the plate is drawn anew, under the new
 * name, and its old file goes then. A plate's page is a product of the
 * plate, made again at will; the pictures are the record.
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';
require_once __DIR__ . '/user_store.php';

/** The largest page the server takes — 200 dpi A4 JPEG lands at 1–2 MB. */
const PLATE_IMAGE_BYTES_MAX = 8_000_000;
/** The kept preview's long edge — a tile in „Belege", a page in the Fundbrowser's stage. */
const PLATE_IMAGE_PREVIEW_EDGE = 640;

function plate_images_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_plate_images');
}

/** Where a page's preview is kept on this server — in the MAKER's directory. */
function plate_image_preview_path(int $ownerId, int $plateId, int $page): string
{
    return user_directory($ownerId) . '/plates/' . $plateId . '-' . $page . '.jpg';
}

/**
 * The file name in the cloud: „Boletus edulis 2026-08-14 DE,THU, Arenshausen
 * 00 Tafel.jpg" — the head the find's pictures carry, then 00.
 */
function plate_image_name(PDO $pdo, array $point, int $page, int $pages): string
{
    require_once __DIR__ . '/cloud_names.php';
    $head = cloud_name_clean(cloud_name_head(cloud_name_species($pdo, $point), $point));
    $tail = '00 Tafel' . ($pages > 1 ? ' ' . $page : '');
    return mb_substr(trim($head . ' ' . $tail), 0, 200) . '.jpg';
}

/**
 * The pages' images of some plates, by plate: [plate id => [{page, hash, width, height}]].
 *
 * @param list<int> $plateIds
 * @return array<int, list<array{page: int, hash: string, width: int, height: int}>>
 */
function plate_images_of(PDO $pdo, array $plateIds): array
{
    if ($plateIds === [] || !plate_images_ready($pdo)) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($plateIds), '?'));
    $stmt = $pdo->prepare("SELECT plate_id, page, hash, width, height FROM map_plate_images
                            WHERE plate_id IN ($marks) ORDER BY plate_id, page");
    $stmt->execute(array_map('intval', $plateIds));
    $out = [];
    foreach ($stmt as $r) {
        $out[(int) $r['plate_id']][] = [
            'page' => (int) $r['page'], 'hash' => (string) $r['hash'],
            'width' => (int) $r['width'], 'height' => (int) $r['height'],
        ];
    }
    return $out;
}

/**
 * Lay one page into the maker's cloud. Returns what lies there now, or
 * ['stored' => false] where there is no cloud to lay it into.
 *
 * $open opens a user's cloud by provider — cloud_storage_for() and
 * cloud_pictures_storage() unless a test hands in the provider played in
 * memory (tests/plate_images.php), as cloud_names_point() takes it.
 *
 * @throws InvalidArgumentException a sentence for the user (not his plate, not a page, not a picture)
 * @throws RuntimeException the cloud refused
 */
function plate_image_store(PDO $pdo, int $userId, int $plateId, int $page, string $hash, string $bytes,
                           ?callable $open = null): array
{
    $stmt = $pdo->prepare('SELECT id, point_id, user_id, layout FROM map_plates WHERE id = ?');
    $stmt->execute([$plateId]);
    $plate = $stmt->fetch();
    if ($plate === false || (int) $plate['user_id'] !== $userId) {
        throw new InvalidArgumentException(t('Diese Tafel ist nicht deine — oder es gibt sie nicht mehr.'));
    }
    // The page count is the stored plate's, not the sender's word.
    $pages = count(json_decode((string) $plate['layout'], true)['pages'] ?? []);
    if ($page < 1 || $page > $pages) {
        throw new InvalidArgumentException(t('Diese Seite gibt es auf der Tafel nicht.'));
    }
    if (!preg_match('/^[0-9a-z]{1,16}$/', $hash)) {
        throw new InvalidArgumentException(t('Die Seite kam unvollständig an.'));
    }
    $info = strlen($bytes) <= PLATE_IMAGE_BYTES_MAX ? @getimagesizefromstring($bytes) : false;
    if (!is_array($info) || ($info['mime'] ?? '') !== 'image/jpeg') {
        throw new InvalidArgumentException(t('Die Seite kam nicht als Bild an.'));
    }

    $s = $open !== null ? $open($userId, 'pcloud') : cloud_pictures_storage($pdo, $userId);
    if ($s === null) {
        return ['stored' => false];
    }

    $q = $pdo->prepare('SELECT * FROM map_points WHERE id = ? LIMIT 1');
    $q->execute([(int) $plate['point_id']]);
    $point = $q->fetch();
    if ($point === false) {
        throw new InvalidArgumentException(t('Diese Tafel ist nicht deine — oder es gibt sie nicht mehr.'));
    }

    $root   = cloud_home_resolve($pdo, $userId, $s);
    $target = cloud_target_folder($s, $root, $pdo, $userId, $point);
    $name   = plate_image_name($pdo, $point, $page, $pages);

    $q = $pdo->prepare('SELECT * FROM map_plate_images WHERE plate_id = ? AND page = ?');
    $q->execute([$plateId, $page]);
    $old = $q->fetch();

    /* REPLACE, ALWAYS: the name is ours, and a file of that name in the
       find's folder is a page of this plate from before (a row lost, a
       plate deleted and made anew) — a second „00 Tafel (1).jpg" beside it
       would be the clutter this feature is meant to spare. */
    $up = storage_upload_bytes($s, $target['folderid'], $bytes, $name, null, true, 'image/jpeg');

    $pdo->prepare(
        'INSERT INTO map_plate_images (plate_id, page, provider, fileid, folderid, name, hash, width, height, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE provider = VALUES(provider), fileid = VALUES(fileid), folderid = VALUES(folderid),
                                 name = VALUES(name), hash = VALUES(hash), width = VALUES(width),
                                 height = VALUES(height), updated_at = NOW()'
    )->execute([$plateId, $page, $s['provider'], (string) $up['fileid'], (string) $target['folderid'],
                mb_substr((string) ($up['name'] ?? $name), 0, 255), $hash, (int) $info[0], (int) $info[1]]);

    /* THE PAGE THE ROW POINTED AT GOES WHENEVER THE UPLOAD IS ANOTHER FILE
       — elsewhere (the find renamed, refiled, another cloud), and ALSO IN
       THE SAME SPOT (22.11.2026). „Replace" is by name, and a name with an
       umlaut can lie up there in the other of its two spellings
       (cloud_name_key): the row says „… Krün 00 Tafel.jpg", the provider
       holds the same letters as other bytes, and the upload lands BESIDE
       the old page instead of over it. Five plates stood twice in the
       maintainer's cloud that way. Where the upload did replace in place,
       both ids are one and nothing is deleted. */
    if ($old !== false && (string) $old['fileid'] !== '' && (string) $old['fileid'] !== (string) $up['fileid']) {
        plate_image_file_delete($pdo, $userId, $old, $open);
    }

    $preview = plate_image_shrink($bytes, PLATE_IMAGE_PREVIEW_EDGE);
    if ($preview !== null) {
        $path = plate_image_preview_path($userId, $plateId, $page);
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0770, true);
        }
        @file_put_contents($path . '.tmp', $preview) !== false && @rename($path . '.tmp', $path);
    }

    return ['stored' => true, 'page' => $page, 'hash' => $hash, 'name' => $name,
            'width' => (int) $info[0], 'height' => (int) $info[1]];
}

/**
 * THE PAGES FOLLOW THEIR FIND (22.11.2026). A page lies in the find's
 * folder under the head the find's pictures carry — and until that day it
 * stayed there when the find moved on. The naming worker
 * (cloud_names_point) took the pictures, their sidecars and the find's
 * „Dateien" folder along; the plate's page was laid afresh only at the
 * next save of the plate. When the folders were rebuilt („Arten/",
 * 18.11.2026) that left pages standing alone in the old tree: three in the
 * maintainer's cloud, and with them the old top folder, which was to be
 * gone („Wenn noch was in Fungi Pilze ist im Grundverzeichnis, dann ist
 * das ein Fehler").
 *
 * WHERE THE FIND IS, THE MAKER'S PICTURES SAY. A page's row knows its
 * folder by id, not by path — so the place it belongs is read off the
 * rows of the maker's pictures at this find in the same cloud, which the
 * worker has just brought in line: one folder there, and the page goes
 * into it. No call to any cloud decides that; the cloud is opened only
 * when a page really moves. While the pictures lie in two folders (a run
 * cut short) the page waits for the next run.
 *
 * WHERE THE MAKER HAS NO PICTURE THERE (a plate made of somebody else's
 * pictures, or of pictures still on this server) only the NAME can tell:
 * a page whose name is no longer the wanted one is placed where the find
 * belongs now (cloud_target_folder). A change that moves such a find
 * without touching the name — a new find number — is not seen here, and
 * the page follows at the next save of the plate, as before.
 *
 * A NAMESAKE IN THE TARGET GOES: the name is ours, and a file of that
 * name is a page from before — plate_image_store() says „REPLACE,
 * ALWAYS", and so does this.
 *
 * Called by the naming worker for every find it looks at; the folder a
 * page left is tidied behind it (storage_prune_empty).
 *
 * @return array{moved: int, failed: int}
 */
function plate_images_follow(PDO $pdo, array $point, ?callable $open = null): array
{
    $out = ['moved' => 0, 'failed' => 0];
    if (!plate_images_ready($pdo) || !table_exists($pdo, 'map_plates')) {
        return $out;
    }
    $q = $pdo->prepare('SELECT i.*, p.user_id, p.layout FROM map_plate_images i JOIN map_plates p ON p.id = i.plate_id
                         WHERE p.point_id = ? ORDER BY i.plate_id, i.page');
    $q->execute([(int) $point['id']]);
    $groups = [];
    foreach ($q->fetchAll() as $row) {
        if ((string) $row['fileid'] !== '') {
            $groups[(int) $row['user_id'] . ':' . $row['provider']][] = $row;
        }
    }
    foreach ($groups as $rows) {
        $user = (int) $rows[0]['user_id'];
        $provider = (string) $rows[0]['provider'];

        // Where this maker's pictures of the find lie in that cloud, by their rows.
        $folders = [];
        $f = $pdo->prepare("SELECT DISTINCT folderid FROM map_cloud_files
                             WHERE point_id = ? AND user_id = ? AND provider = ? AND fileid <> '' AND folderid <> ''");
        $f->execute([(int) $point['id'], $user, $provider]);
        foreach ($f->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $folders[(string) $id] = true;
        }
        if (column_exists($pdo, 'map_photos', 'cloud_folderid')) {
            $f = $pdo->prepare("SELECT DISTINCT cloud_folderid FROM map_photos
                                 WHERE point_id = ? AND user_id = ? AND cloud_fileid IS NOT NULL AND cloud_fileid <> ''
                                   AND cloud_folderid IS NOT NULL AND cloud_folderid <> ''
                                   AND COALESCE(NULLIF(cloud_provider, ''), 'pcloud') = ?");
            $f->execute([(int) $point['id'], $user, $provider]);
            foreach ($f->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $folders[(string) $id] = true;
            }
        }
        if (count($folders) > 1) {
            continue;   // the pictures are between two folders — the next run knows where the find is
        }
        $target = $folders === [] ? null : (string) array_key_first($folders);

        $todo = [];
        foreach ($rows as $row) {
            $pages = count(json_decode((string) $row['layout'], true)['pages'] ?? []);
            $want = plate_image_name($pdo, $point, (int) $row['page'], max(1, $pages));
            if (cloud_name_key((string) $row['name']) !== cloud_name_key($want)
                || ($target !== null && (string) $row['folderid'] !== $target)) {
                $todo[] = [$row, $want];
            }
        }
        if ($todo === []) {
            continue;
        }

        try {
            $s = $open !== null ? $open($user, $provider) : cloud_storage_for($pdo, $user, $provider);
            if ($s === null) {
                continue;
            }
            $target ??= (string) cloud_target_folder($s, cloud_home_resolve($pdo, $user, $s), $pdo, $user, $point)['folderid'];
            $here = [];
            foreach (storage_folder_files($s, $target) as $name => $file) {
                $here[cloud_name_key((string) $name)] = (string) $file['fileid'];
            }
            $left = [];
            foreach ($todo as [$row, $want]) {
                $holder = $here[cloud_name_key($want)] ?? null;
                if ($holder !== null && $holder !== (string) $row['fileid']) {
                    storage_file_delete($s, $holder);   // a page from before
                }
                $id = storage_file_place($s, (string) $row['fileid'], $target, $want);
                $pdo->prepare('UPDATE map_plate_images SET fileid = ?, folderid = ?, name = ? WHERE plate_id = ? AND page = ?')
                    ->execute([$id, $target, mb_substr($want, 0, 255), (int) $row['plate_id'], (int) $row['page']]);
                $here[cloud_name_key($want)] = $id;
                if ((string) $row['folderid'] !== '' && (string) $row['folderid'] !== $target) {
                    $left[(string) $row['folderid']] = true;
                }
                $out['moved']++;
            }
            foreach (array_keys($left) as $folder) {
                storage_prune_empty($pdo, $user, $s, (string) $folder);
            }
        } catch (RuntimeException $e) {
            error_log('plate_images_follow (' . (int) $point['id'] . '): ' . $e->getMessage());
            $out['failed']++;
        }
    }
    return $out;
}

/**
 * The pages a plate no longer has: their files, rows and previews go —
 * after a save that took pages away (plate_save), or all of them when the
 * plate itself goes ($pages = 0). Best effort: a file the cloud will not
 * delete now stays there as clutter, never as a loss.
 */
function plate_images_prune(PDO $pdo, int $userId, int $plateId, int $pages, ?callable $open = null): void
{
    if (!plate_images_ready($pdo)) {
        return;
    }
    $q = $pdo->prepare('SELECT * FROM map_plate_images WHERE plate_id = ? AND page > ?');
    $q->execute([$plateId, $pages]);
    foreach ($q->fetchAll() as $row) {
        plate_image_file_delete($pdo, $userId, $row, $open);
        $pdo->prepare('DELETE FROM map_plate_images WHERE plate_id = ? AND page = ?')
            ->execute([$plateId, (int) $row['page']]);
        @unlink(plate_image_preview_path($userId, $plateId, (int) $row['page']));
    }
}

/** One page's file in the cloud goes — logged, not thrown, when the cloud says no. */
function plate_image_file_delete(PDO $pdo, int $userId, array $row, ?callable $open = null): void
{
    try {
        $s = $open !== null ? $open($userId, (string) $row['provider']) : cloud_storage_for($pdo, $userId, (string) $row['provider']);
        if ($s !== null && $s['provider'] === $row['provider']) {
            storage_picture_delete($pdo, $userId, $s, (string) $row['fileid']);
        }
    } catch (RuntimeException $e) {
        error_log('plate_image_file_delete (' . $row['plate_id'] . '/' . $row['page'] . '): ' . $e->getMessage());
    }
}

/** A JPEG drawn into a box of $edge on its long side; null where GD cannot. */
function plate_image_shrink(string $bytes, int $edge): ?string
{
    // A page that declares more pixels than the server holds is not drawn (picture_fence.php).
    if (!function_exists('imagecreatefromstring') || !picture_decodable($bytes)) {
        return null;
    }
    $image = @imagecreatefromstring($bytes);
    if ($image === false) {
        return null;
    }
    $w = imagesx($image);
    $h = imagesy($image);
    $scale = min(1.0, $edge / max($w, $h));
    $tw = max(1, (int) round($w * $scale));
    $th = max(1, (int) round($h * $scale));
    $small = imagecreatetruecolor($tw, $th);
    imagecopyresampled($small, $image, 0, 0, 0, 0, $tw, $th, $w, $h);
    imagedestroy($image);
    ob_start();
    imagejpeg($small, null, 84);
    imagedestroy($small);
    $out = (string) ob_get_clean();
    return $out === '' ? null : $out;
}
