<?php
/**
 * THE PATCHES OF „ENTFERNEN" — where the AI's pixels are kept (11.11.2026).
 *
 * A place removed with the AI (assets/js/photowindow.js, „Entfernen" in the
 * tab Ausbessern) is described in the picture's `develop` like every other
 * retouching — path, radius, soft edge — but what fills it was invented by
 * a model on the user's device and cannot be described. So each piece
 * leaves one PNG of 512 × 512 pixels behind, transparent outside the place
 * it fills, and that PNG is kept IN THE PICTURE'S CLOUD, in „Bearbeitung"
 * beside the sidecar (the decisions of 10.11.2026: „kleine PNGs in
 * der Cloud", the subfolder, and NO CLOUD, NO EDITING — this server never
 * holds a patch, as it never holds a picture).
 *
 * NAMED AFTER THE PICTURE (develop_patch_name in cloud.php), so the ways
 * that already follow the sidecar follow the patches too: the refiling and
 * the deleting through storage_sidecars_of, the renaming in
 * cloud_names_move, the tidying through storage_stray_sidecars.
 *
 * WHEN THEY GO UP: with the save of the `develop` that names them. The
 * browser sends the patches it has not stored yet beside the settings, and
 * they are written BEFORE the settings — a stored `develop` never names a
 * patch the cloud does not hold. A patch whose place is taken out again is
 * deleted on the next save (develop_patches_gone).
 *
 * „ERWEITERN" LAYS ITS BORDER HERE TOO (15.11.2026): a crop that reaches
 * past the picture has what lies outside invented by the same models, as
 * ONE image over the crop (`geo.fill` in the `develop`, assets/js/develop.js).
 * That image is cut into the same squares of 512 and kept under the same
 * names, so nothing here had to learn a second kind of file — only
 * develop_patch_ids, which now names the border's squares as well.
 *
 * WHO READS THEM: everybody who may see the picture, through
 * pages/develop_patch.php — the patch comes from the cloud of whoever's
 * picture it is, through this server, like the picture itself
 * (pages/photo.php, pages/cloud_thumb.php: a provider's link is a
 * credential and never reaches a browser).
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';

/** A patch is the model's square: never another size. */
const DEVELOP_PATCH_SIDE = 512;
/** Above any honest patch: 512 × 512 RGBA raw is 1 MB, and PNG only shrinks that. */
const DEVELOP_PATCH_MAX_BYTES = 1536 * 1024;
/** How many patches one save may bring — a long stroke is a handful; the rest come with the next save. */
const DEVELOP_PATCHES_PER_SAVE = 24;

/**
 * The cloud a picture lies in, opened in its OWNER's name — the voucher
 * photo's row (`photo`) or the recording's (`cloud`); null where it cannot
 * be opened. Whoever saves or looks, the patches lie beside the picture.
 */
function develop_patches_cloud(PDO $pdo, string $kind, array $row): ?array
{
    try {
        if ($kind === 'photo') {
            require_once __DIR__ . '/photo_cloud.php';
            return photo_cloud_storage($pdo, (int) $row['user_id'], $row);
        }
        return cloud_storage_for($pdo, (int) $row['user_id'], (string) ($row['provider'] ?? '') ?: 'pcloud');
    } catch (Throwable $e) {
        error_log('develop_patches_cloud (' . $kind . ' ' . (int) ($row['id'] ?? 0) . '): ' . $e->getMessage());
        return null;
    }
}

/** The picture's own id at its provider, off its row. */
function develop_patches_fileid(string $kind, array $row): string
{
    return (string) ($kind === 'photo' ? ($row['cloud_fileid'] ?? '') : ($row['fileid'] ?? ''));
}

/**
 * The patch ids a `develop` names — the pieces of its AI spots, the shown
 * ones and the other model's kept beside them (`other`, 14.11.2026): a
 * save that names a kept patch must not have it deleted as dropped.
 *
 * @return list<string>
 */
function develop_patch_ids(?array $develop): array
{
    $ids = [];
    foreach ((array) ($develop['spots'] ?? []) as $spot) {
        // Whatever its method: a place shown as „Angleichen" keeps the fills of „Entfernen" beside it (14.11.2026).
        if (!is_array($spot)) {
            continue;
        }
        foreach (array_merge((array) ($spot['pieces'] ?? []), (array) ($spot['other'] ?? [])) as $piece) {
            if (is_array($piece) && is_string($piece['id'] ?? null)) {
                $ids[] = $piece['id'];
            }
        }
    }
    // „Erweitern" (15.11.2026): the filled border of a crop past the picture lies here in the same squares (geo.fill).
    // … and what the strong model made of single areas of it, shown or kept (geo.fill.fine).
    $fill = (array) ($develop['geo']['fill'] ?? []);
    foreach (array_merge([$fill], (array) ($fill['fine'] ?? [])) as $image) {
        foreach (is_array($image) ? (array) ($image['pieces'] ?? []) : [] as $piece) {
            if (is_array($piece) && is_string($piece['id'] ?? null)) {
                $ids[] = $piece['id'];
            }
        }
    }
    return array_values(array_unique($ids));
}

/**
 * The patch ids of EVERY edit of a picture — the valid one and the others
 * beside it (migration 0192). A patch may be deleted only when no edit
 * names it any more: saving edit 1 must not take away what edit 2 shows.
 *
 * @return list<string>
 */
function develop_patch_ids_all(?array $develop, ?array $versions): array
{
    $ids = develop_patch_ids($develop);
    foreach ($versions['list'] ?? [] as $d) {
        if (is_array($d)) {
            $ids = array_merge($ids, develop_patch_ids($d));
        }
    }
    return array_values(array_unique($ids));
}

/** The same off a picture's row, either table. @return list<string> */
function develop_row_patch_ids(array $row): array
{
    return develop_patch_ids_all(photo_develop_read($row['develop'] ?? null),
        photo_develop_versions_read($row['develop_versions'] ?? null));
}

/**
 * The patch ids the OTHERS' edits of a picture name (map_picture_edits,
 * migration 0194; stage 4 of „Fremdbearbeitungen", 12.11.2026). Their
 * patches lie in the same „Bearbeitung" as the photographer's own — „ein
 * Foto, eine Bearbeitung, eine Cloud. Alles beim Besitzer" — so whatever
 * asks which patches a picture still needs (the tidying after a save, the
 * reader, the move to another cloud) asks this too. `$except`: one edit
 * left out, the one whose own save is just deciding what it lets go.
 *
 * @return list<string>
 */
function develop_foreign_patch_ids(PDO $pdo, string $kind, int $id, ?int $except = null): array
{
    if (!table_exists($pdo, 'map_picture_edits')) {
        return [];
    }
    $stmt = $pdo->prepare(
        "SELECT develop FROM map_picture_edits
          WHERE picture_kind = ? AND picture_id = ? AND id <> ?
            AND (develop LIKE '%\"ai\"%' OR develop LIKE '%\"fill\"%')"
    );
    $stmt->execute([$kind, $id, $except ?? 0]);
    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $json) {
        $ids = array_merge($ids, develop_patch_ids(photo_develop_read($json)));
    }
    return array_values(array_unique($ids));
}

/** Every patch id a picture's edits name — its photographer's and everybody else's. @return list<string> */
function develop_picture_patch_ids(PDO $pdo, string $kind, array $row): array
{
    return array_values(array_unique(array_merge(develop_row_patch_ids($row),
        develop_foreign_patch_ids($pdo, $kind, (int) $row['id']))));
}

/** Is this a PNG of the patch's size? Read off the header — nothing is decoded. */
function develop_patch_png(string $bytes): bool
{
    if (strlen($bytes) < 33 || strlen($bytes) > DEVELOP_PATCH_MAX_BYTES
        || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n" || substr($bytes, 12, 4) !== 'IHDR') {
        return false;
    }
    $size = unpack('Nw/Nh', substr($bytes, 16, 8));
    return $size['w'] === DEVELOP_PATCH_SIDE && $size['h'] === DEVELOP_PATCH_SIDE;
}

/**
 * The patches a save brought — { id: base64 PNG } — checked and decoded.
 * Only ids the saved edits name are taken — the valid `develop` or one of
 * the others beside it (`$versions`, 0192): a patch nothing points at
 * would lie in the cloud for nothing.
 *
 * @return array<string, string> id => PNG bytes
 * @throws InvalidArgumentException
 */
function develop_patches_decode(mixed $input, ?array $develop, ?array $versions = null): array
{
    if (!is_array($input)) {
        throw new InvalidArgumentException(t('Unbrauchbare Bearbeitung.'));
    }
    if (count($input) > DEVELOP_PATCHES_PER_SAVE) {
        throw new InvalidArgumentException(t('Unbrauchbare Bearbeitung.'));
    }
    $named = array_flip(develop_patch_ids_all($develop, $versions));
    $out = [];
    foreach ($input as $id => $data) {
        $id = (string) $id;
        if (!isset($named[$id]) || !is_string($data)) {
            continue;
        }
        $bytes = base64_decode($data, true);
        if ($bytes === false || !develop_patch_png($bytes)) {
            throw new InvalidArgumentException(t('Unbrauchbare Bearbeitung.'));
        }
        $out[$id] = $bytes;
    }
    return $out;
}

/**
 * Lay patches into „Bearbeitung" beside the picture `$fileid` — all or the
 * save stops: the caller stores the `develop` only after this returned.
 *
 * @param array<string, string> $patches id => PNG bytes
 * @throws RuntimeException
 */
function develop_patches_store(array $s, string $fileid, array $patches): void
{
    if ($patches === []) {
        return;
    }
    $meta = storage_file_meta($s, $fileid);
    $name = (string) ($meta['name'] ?? '');
    $folder = (string) ($meta['parent'] ?? '');
    if ($name === '' || $folder === '' || $folder === '0') {
        throw new RuntimeException('develop_patches_store: the picture ' . $fileid . ' was not found');
    }
    $into = storage_sidecar_folder($s, $folder, true);
    foreach ($patches as $id => $bytes) {
        // Replace: the same id is the same patch — a save sent twice writes it twice, never two files.
        storage_upload_bytes($s, $into, $bytes, develop_patch_name($name, (string) $id), null, true, 'image/png');
    }
}

/**
 * The patches of the picture `$fileid` that its `develop` no longer names
 * go — best effort: a left-over is clutter, and it goes with the picture
 * at the latest (storage_sidecars_of). Only asked when a save took ids
 * away, so an ordinary save costs no listing.
 *
 * @param list<string> $gone
 */
function develop_patches_gone(array $s, string $fileid, array $gone): void
{
    if ($gone === []) {
        return;
    }
    try {
        $meta = storage_file_meta($s, $fileid);
        $name = (string) ($meta['name'] ?? '');
        $folder = (string) ($meta['parent'] ?? '');
        if ($name === '' || $folder === '') {
            return;
        }
        $sub = storage_sidecar_folder($s, $folder, false);
        if ($sub === null) {
            return;
        }
        $files = storage_folder_files($s, $sub);
        foreach ($gone as $id) {
            $f = $files[develop_patch_name($name, $id)] ?? null;
            if ($f !== null) {
                storage_file_delete($s, $f['fileid']);
            }
        }
    } catch (Throwable $e) {
        error_log('develop_patches_gone (' . $fileid . '): ' . get_class($e) . ': ' . $e->getMessage());
    }
}

/**
 * One patch's bytes, out of the picture's cloud — null where it is not
 * (gone by hand, or the picture renamed by hand without it).
 */
function develop_patch_bytes(array $s, string $fileid, string $id): ?string
{
    $meta = storage_file_meta($s, $fileid);
    $name = (string) ($meta['name'] ?? '');
    $folder = (string) ($meta['parent'] ?? '');
    if ($name === '' || $folder === '') {
        return null;
    }
    $sub = storage_sidecar_folder($s, $folder, false);
    $f = $sub === null ? null : (storage_folder_files($s, $sub)[develop_patch_name($name, $id)] ?? null);
    if ($f === null) {
        return null;
    }
    $bytes = storage_file_bytes($s, $f['fileid'], DEVELOP_PATCH_MAX_BYTES);
    return develop_patch_png($bytes) ? $bytes : null;
}

/**
 * A picture moved to ANOTHER cloud (includes/cloud_moves.php) takes its
 * patches along: the sidecar is written anew there from our rows, but a
 * patch exists nowhere else — left behind, the place would come back.
 * Best effort per patch, like the sidecar.
 *
 * @param list<string> $ids
 */
function develop_patches_carry(array $from, string $oldFileid, array $to, string $newFileid, array $ids): void
{
    if ($ids === []) {
        return;
    }
    $carried = [];
    foreach ($ids as $id) {
        try {
            $bytes = develop_patch_bytes($from, $oldFileid, $id);
            if ($bytes !== null) {
                $carried[$id] = $bytes;
            }
        } catch (RuntimeException $e) {
            error_log('develop_patches_carry read (' . $oldFileid . ' ' . $id . '): ' . $e->getMessage());
        }
    }
    try {
        develop_patches_store($to, $newFileid, $carried);
    } catch (RuntimeException $e) {
        error_log('develop_patches_carry write (' . $newFileid . '): ' . $e->getMessage());
    }
}
