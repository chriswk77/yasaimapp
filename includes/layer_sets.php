<?php
/**
 * „EBENEN" — the work on a set of layers, kept (11.11.2026,
 * migration 0191; the tool is assets/js/blend.js).
 *
 * Several pictures of one find that show the same thing — for him the
 * three renderings Helicon Focus makes of one stack — lie over each other,
 * and each but the lowest shows only where its mask was painted. Painting
 * takes a while, so what he painted must stand when he comes back.
 *
 * TWO HALVES, as with everything that hangs on a picture:
 *
 *   THE SET is a row (map_layer_sets): which pictures, in which order,
 *   which switched on, which with a mask. It is its maker's alone.
 *
 *   THE MASKS are pictures themselves — one byte per pixel of a camera
 *   picture — and lie where the pictures lie: in the cloud of the picture
 *   they belong to, in „Bearbeitung", as „<picture>.ebene.png" (cloud.php,
 *   layer_mask_name). This server never holds one, as it never holds a
 *   picture („ohne Cloud keine Bearbeitung", 10.11.2026).
 *
 * NAMED AFTER ITS PICTURE, the mask is carried by the ways that carry the
 * sidecar and the retouching's patches: refiling and renaming move it
 * along, and with its picture gone it is a stray the tidying takes
 * (storage_sidecars_of, cloud_names_move, storage_stray_sidecars).
 *
 * THE MASK FIRST, THEN THE ROW. The browser sends each changed mask by
 * itself (a file field — a megabyte as JSON would be a third more, and
 * the host caps a request), and only then the row that says „this layer
 * has a mask". A row never names a mask the cloud does not hold; a mask
 * the row does not name yet is replaced by the next save.
 *
 * ONLY ONE'S OWN PICTURES ON ONE'S OWN FIND. A co-owner's picture on the
 * find lies in HIS cloud, and nothing of ours is written there.
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';
require_once __DIR__ . '/develop_patches.php';

/** The ground and three more — the tool's own limit (blend.js, LAYERS_MAX). */
const LAYER_SETS_MAX_LAYERS = 4;
/** A mask as a file: the host takes two megabytes per upload; the tool halves a mask that is more. */
const LAYER_MASK_MAX_BYTES = 2 * 1024 * 1024;
/** The longest side a mask is painted at (blend.js, EDGE). */
const LAYER_MASK_EDGE = 4096;

function layer_sets_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_layer_sets');
}

/**
 * The layers as stored — 2 to 4 distinct picture keys, each with whether
 * it is switched on and whether it carries a mask — or null for anything
 * else.
 *
 * ALIGNED RENDERINGS (11.11.2026, blend.js step 6): one layer
 * may be the FRAME — the picture whose pixels the masks are painted in —
 * and a layer cut otherwise carries its MAP from the frame to itself, six
 * numbers in shares of width and height. Both are left out where they say
 * nothing (no frame named: the first; no map: the same cut), so a set
 * begun before keeps its shape. A map that would fold the picture flat or
 * throw it far off is no alignment and is dropped.
 *
 * @return ?list<array{key: string, shown: bool, mask: bool, hdr?: true, crop?: false, frame?: true, align?: list<float>, exposure?: int}>
 */
function layer_sets_clean(mixed $layers): ?array
{
    if (!is_array($layers) || count($layers) < 2 || count($layers) > LAYER_SETS_MAX_LAYERS) {
        return null;
    }
    $out = [];
    $framed = false;
    $first = true;
    foreach ($layers as $l) {
        $key = is_array($l) ? (string) ($l['key'] ?? '') : '';
        if (preg_match('/^(photo|cloud):[1-9][0-9]{0,9}$/', $key) !== 1 || isset($out[$key])) {
            return null;
        }
        $one = ['key' => $key, 'shown' => !array_key_exists('shown', $l) || !empty($l['shown']), 'mask' => !empty($l['mask'])];
        // The set's tab (blend.js, step 9): „HDR" rides on the first layer, Stacking is its absence.
        if ($first && !empty($l['hdr'])) {
            $one['hdr'] = true;
        }
        // The common area switched off for the set (blend.js, step 10): on the first layer too, kept only when false.
        if ($first && array_key_exists('crop', $l) && $l['crop'] === false) {
            $one['crop'] = false;
        }
        $first = false;
        if (!empty($l['frame']) && !$framed) {
            $one['frame'] = true;
            $framed = true;
        }
        $align = layer_align_clean($l['align'] ?? null);
        if ($align !== null) {
            $one['align'] = $align;
        }
        // The layer's own exposure (blend.js, step 8): a whole number −100 … 100, left out at 0.
        $exposure = $l['exposure'] ?? 0;
        if ((is_int($exposure) || is_float($exposure)) && is_finite((float) $exposure)) {
            $exposure = (int) round(max(-100, min(100, (float) $exposure)));
            if ($exposure !== 0) {
                $one['exposure'] = $exposure;
            }
        }
        $out[$key] = $one;
    }
    return array_values($out);
}

/**
 * A layer's map, six finite numbers rounded to a millionth — a millionth
 * of four thousand pixels is nothing one sees — or null: none given, not
 * six numbers (or eight: a tilted layer's, with its denominator), or a map that shrinks or stretches the picture more than
 * fourfold or lays it more than its own size away.
 *
 * @return ?list<float>
 */
function layer_align_clean(mixed $align): ?array
{
    // Six numbers, or eight where the layer is tilted against the frame (blend.js, align.js: the denominator g·x + h·y + 1).
    if (!is_array($align) || (count($align) !== 6 && count($align) !== 8) || !array_is_list($align)) {
        return null;
    }
    $m = [];
    foreach ($align as $v) {
        if (!is_int($v) && !is_float($v)) {
            return null;
        }
        $f = (float) $v;
        if (!is_finite($f) || abs($f) > 10) {
            return null;
        }
        $m[] = round($f, 6);
    }
    $det = $m[0] * $m[4] - $m[1] * $m[3];
    if ($det < 1 / 16 || $det > 16 || abs($m[2]) > 2 || abs($m[5]) > 2) {
        return null;
    }
    if (count($m) === 8) {
        // The denominator must stay well above zero over the whole frame — or the layer folds.
        if (abs($m[6]) + abs($m[7]) > .6) {
            return null;
        }
        // A tilt that says nothing is no tilt: the map keeps its six numbers.
        if ($m[6] == 0.0 && $m[7] == 0.0) {
            $m = array_slice($m, 0, 6);
        }
    }
    return $m;
}

/**
 * One picture by its key — the user's own, on this find — as its kind and
 * row; null for a stranger's, another find's, or none.
 *
 * @return ?array{kind: string, row: array}
 */
function layer_picture(PDO $pdo, int $userId, int $pointId, string $key): ?array
{
    [$kind, $id] = explode(':', $key) + ['', ''];
    $table = match ($kind) {
        'photo' => 'map_photos',
        'cloud' => 'map_cloud_files',
        default => null,
    };
    if ($table === null || !table_exists($pdo, $table)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = ? AND user_id = ? AND point_id = ?');
    $stmt->execute([(int) $id, $userId, $pointId]);
    $row = $stmt->fetch();
    return $row === false ? null : ['kind' => $kind, 'row' => $row];
}

// --- the mask in the cloud ---------------------------------------------------

/** Is this a PNG a mask can be? Read off the header — nothing is decoded. */
function layer_mask_png(string $bytes): bool
{
    if (strlen($bytes) < 33 || strlen($bytes) > LAYER_MASK_MAX_BYTES
        || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n" || substr($bytes, 12, 4) !== 'IHDR') {
        return false;
    }
    $size = unpack('Nw/Nh', substr($bytes, 16, 8));
    return $size['w'] >= 1 && $size['h'] >= 1 && $size['w'] <= LAYER_MASK_EDGE && $size['h'] <= LAYER_MASK_EDGE;
}

/** Where a picture lies: its name and folder at the provider — null when it is gone. */
function layer_mask_place(array $s, string $fileid): ?array
{
    $meta = storage_file_meta($s, $fileid);
    $name = (string) ($meta['name'] ?? '');
    $folder = (string) ($meta['parent'] ?? '');
    return $name === '' || $folder === '' || $folder === '0' ? null : ['name' => $name, 'folder' => $folder];
}

/**
 * Lay a picture's mask into „Bearbeitung" beside it — replacing the one
 * that lay there.
 *
 * @throws RuntimeException
 */
function layer_mask_put(array $s, string $fileid, string $bytes): void
{
    $at = layer_mask_place($s, $fileid);
    if ($at === null) {
        throw new RuntimeException('layer_mask_put: the picture ' . $fileid . ' was not found');
    }
    storage_upload_bytes($s, storage_sidecar_folder($s, $at['folder'], true), $bytes,
        layer_mask_name($at['name']), null, true, 'image/png');
}

/** A picture's mask, its file at the provider — null where there is none. */
function layer_mask_file(array $s, string $fileid): string|int|null
{
    $at = layer_mask_place($s, $fileid);
    $sub = $at === null ? null : storage_sidecar_folder($s, $at['folder'], false);
    return $sub === null ? null : (storage_folder_files($s, $sub)[layer_mask_name($at['name'])]['fileid'] ?? null);
}

/** A picture's mask as bytes — null where there is none, or what lies there is no mask. */
function layer_mask_get(array $s, string $fileid): ?string
{
    $file = layer_mask_file($s, $fileid);
    if ($file === null) {
        return null;
    }
    $bytes = storage_file_bytes($s, $file, LAYER_MASK_MAX_BYTES);
    return layer_mask_png($bytes) ? $bytes : null;
}

/** A picture's mask taken away — and „Bearbeitung" with it when that was its last file. */
function layer_mask_remove(array $s, string $fileid): void
{
    $at = layer_mask_place($s, $fileid);
    $sub = $at === null ? null : storage_sidecar_folder($s, $at['folder'], false);
    $file = $sub === null ? null : (storage_folder_files($s, $sub)[layer_mask_name($at['name'])]['fileid'] ?? null);
    if ($file === null) {
        return;
    }
    storage_file_delete($s, $file);
    if (in_array($s['provider'], ['pcloud', 'onedrive'], true)
        && storage_folder_files($s, $sub) === [] && storage_subfolders($s, $sub) === []) {
        storage_folder_delete_empty($s, $sub);
    }
}

/** A picture's mask taken away, whatever the cloud says — a left-over is clutter, never a loss. */
function layer_mask_forget(PDO $pdo, array $picture): void
{
    try {
        $fileid = develop_patches_fileid($picture['kind'], $picture['row']);
        $s = $fileid === '' ? null : develop_patches_cloud($pdo, $picture['kind'], $picture['row']);
        if ($s !== null) {
            layer_mask_remove($s, $fileid);
        }
    } catch (Throwable $e) {
        error_log('layer_mask_forget (' . $picture['kind'] . ' ' . (int) $picture['row']['id'] . '): ' . $e->getMessage());
    }
}

// --- the sets ----------------------------------------------------------------

/** A stored row as the tool reads it. */
function layer_set_shape(array $row): array
{
    return [
        'id'         => (int) $row['id'],
        'layers'     => layer_sets_clean(json_decode((string) $row['layers'], true)) ?? [],
        'updated_at' => (string) $row['updated_at'],
    ];
}

/**
 * The sets one works on at a find. A set whose picture is gone — deleted,
 * moved to another find — cannot be continued: it goes, and what masks its
 * other pictures still carry go with it.
 *
 * @return list<array{id: int, layers: list<array>, updated_at: string}>
 */
function layer_sets_of(PDO $pdo, int $userId, int $pointId): array
{
    if (!layer_sets_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM map_layer_sets WHERE user_id = ? AND point_id = ? ORDER BY updated_at DESC, id DESC');
    $stmt->execute([$userId, $pointId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $set = layer_set_shape($row);
        $whole = $set['layers'] !== [];
        foreach ($set['layers'] as $l) {
            $whole = $whole && layer_picture($pdo, $userId, $pointId, $l['key']) !== null;
        }
        if ($whole) {
            $out[] = $set;
        } else {
            layer_set_drop($pdo, $userId, $pointId, $set);
        }
    }
    return $out;
}

/** One set and the masks it names — those in `$keep` (picture keys) stay. */
function layer_set_drop(PDO $pdo, int $userId, int $pointId, array $set, array $keep = []): void
{
    foreach ($set['layers'] as $l) {
        if (!$l['mask'] || in_array($l['key'], $keep, true)) {
            continue;
        }
        $picture = layer_picture($pdo, $userId, $pointId, $l['key']);
        if ($picture !== null) {
            layer_mask_forget($pdo, $picture);
        }
    }
    $pdo->prepare('DELETE FROM map_layer_sets WHERE id = ? AND user_id = ?')->execute([(int) $set['id'], $userId]);
}

/**
 * Keep a set. The one of the same pictures is written over; another that
 * shares a picture with it gives way — a picture lies in one set, since it
 * has one mask. A layer that lost its mask loses the file.
 *
 * @param list<array{key: string, shown: bool, mask: bool}> $layers  the CLEAN shape
 * @return array{id: int, layers: list<array>, updated_at: string}
 * @throws InvalidArgumentException a picture that is not one's own on this find
 */
function layer_set_save(PDO $pdo, int $userId, int $pointId, array $layers): array
{
    $keys = array_column($layers, 'key');
    foreach ($keys as $key) {
        if (layer_picture($pdo, $userId, $pointId, $key) === null) {
            throw new InvalidArgumentException(t('Ebenen gehen nur mit deinen eigenen Bildern an diesem Fund.'));
        }
    }
    $sorted = $keys;
    sort($sorted);
    $masked = array_column(array_filter($layers, static fn(array $l): bool => $l['mask']), 'key');

    $same = null;
    $before = [];
    foreach (layer_sets_of($pdo, $userId, $pointId) as $set) {
        $theirs = array_column($set['layers'], 'key');
        if (array_intersect($theirs, $keys) === []) {
            continue;
        }
        $theirsSorted = $theirs;
        sort($theirsSorted);
        foreach ($set['layers'] as $l) {
            if ($l['mask']) {
                $before[] = $l['key'];
            }
        }
        if ($same === null && $theirsSorted === $sorted) {
            $same = $set;
        } else {
            layer_set_drop($pdo, $userId, $pointId, $set, $masked);
        }
    }
    // What carried a mask in the set written over and carries none now.
    foreach (array_diff($same === null ? [] : array_intersect($before, $keys), $masked) as $key) {
        $picture = layer_picture($pdo, $userId, $pointId, $key);
        if ($picture !== null) {
            layer_mask_forget($pdo, $picture);
        }
    }

    $json = json_encode($layers, JSON_UNESCAPED_SLASHES);
    if ($same !== null) {
        $pdo->prepare('UPDATE map_layer_sets SET layers = ?, updated_at = NOW() WHERE id = ? AND user_id = ?')
            ->execute([$json, $same['id'], $userId]);
        $id = $same['id'];
    } else {
        $pdo->prepare('INSERT INTO map_layer_sets (point_id, user_id, layers, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())')
            ->execute([$pointId, $userId, $json]);
        $id = (int) $pdo->lastInsertId();
    }
    $stmt = $pdo->prepare('SELECT * FROM map_layer_sets WHERE id = ?');
    $stmt->execute([$id]);
    return layer_set_shape($stmt->fetch());
}

/**
 * One mask into its picture's cloud — the browser's upload, checked.
 *
 * @throws InvalidArgumentException|RuntimeException
 */
function layer_mask_store(PDO $pdo, int $userId, int $pointId, string $key, string $bytes): void
{
    $picture = layer_picture($pdo, $userId, $pointId, $key);
    if ($picture === null) {
        throw new InvalidArgumentException(t('Ebenen gehen nur mit deinen eigenen Bildern an diesem Fund.'));
    }
    if (!layer_mask_png($bytes)) {
        throw new InvalidArgumentException(t('Die Maske kam nicht als Bild an.'));
    }
    $fileid = develop_patches_fileid($picture['kind'], $picture['row']);
    if ($fileid === '') {
        throw new InvalidArgumentException(t('Bearbeiten geht nur bei Bildern, die in einer Cloud liegen.'));
    }
    $s = develop_patches_cloud($pdo, $picture['kind'], $picture['row']);
    if ($s === null) {
        throw new RuntimeException(t('Die Cloud des Bildes ist gerade nicht erreichbar.'));
    }
    layer_mask_put($s, $fileid, $bytes);
}

/**
 * The mask of one layer of one's own set, as bytes — null where the set
 * is not one's own, names no mask for this picture, or the cloud holds none.
 *
 * @throws RuntimeException the cloud does not answer
 */
function layer_mask_read(PDO $pdo, int $userId, int $setId, string $key): ?string
{
    if (!layer_sets_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM map_layer_sets WHERE id = ? AND user_id = ?');
    $stmt->execute([$setId, $userId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }
    foreach (layer_set_shape($row)['layers'] as $l) {
        if ($l['key'] !== $key || !$l['mask']) {
            continue;
        }
        $picture = layer_picture($pdo, $userId, (int) $row['point_id'], $key);
        $fileid = $picture === null ? '' : develop_patches_fileid($picture['kind'], $picture['row']);
        if ($fileid === '') {
            return null;
        }
        $s = develop_patches_cloud($pdo, $picture['kind'], $picture['row']);
        if ($s === null) {
            throw new RuntimeException(t('Die Cloud des Bildes ist gerade nicht erreichbar.'));
        }
        return layer_mask_get($s, $fileid);
    }
    return null;
}
