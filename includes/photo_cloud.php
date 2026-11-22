<?php
/**
 * Voucher photos into the user's own storage.
 *
 * WHAT THIS IS FOR (19.08.2026). A photo from the phone lands on
 * this server because it has to: the phone hands it over the moment it has
 * reception, and that moment has nothing to do with anyone looking at a
 * screen. But it should not STAY here — several users' pictures on a shared
 * host, counted against a quota, backed up nowhere in particular. So this
 * server is a waiting room, not an archive: the picture goes on into the
 * storage that belongs to the user, and the local copy goes away.
 *
 * ONLY THE BYTES MOVE. The row in map_photos stays, and with it everything
 * that makes the picture a record rather than a file: when it was taken,
 * with what, where, in which order it stands in the gallery, which find it
 * belongs to. Three columns say where the pixels went (migration 0056).
 *
 * WITHOUT A GENUS IT GOES TO "Unbestimmt". The folders are taxonomic —
 * kingdom, group, division, letter, genus, species, find day — and a find
 * that carries no name yet fits nowhere in them. Leaving the picture here
 * until the determination comes was the first answer; the maintainer's is
 * better (19.08.2026): a folder of its own beats waiting, because the
 * determination may take a winter and the picture should not sit on a
 * shared host for it.
 *
 * AND IT DOES NOT STAY THERE. As soon as the find carries a name, the next
 * sweep moves the file into the folder it belongs in — the same self-
 * rearranging the letter compartments already do. Since 28.09.2026 the same
 * holds for every later change of the find's folder (the species after
 * „spec.", a corrected determination), for the recordings too
 * (cloud_refile_due, recordings_refile).
 *
 * NOTHING IS DELETED UNTIL THE PROVIDER HAS CONFIRMED THE SIZE. A truncated
 * upload that still answered "result 0" would otherwise be the only copy
 * there is. Every provider's upload checks it (storage_upload_bytes).
 *
 * EVERY PICTURE KNOWS ITS CLOUD (26.09.2026, the first step of „alle Clouds
 * richtig nutzen"). cloud_provider on the row says where the bytes lie, and
 * everything that reads, moves or deletes a picture knocks at THAT door
 * through the storage seam (storage_* in cloud.php) — not at whichever
 * cloud the account happens to have connected now. New pictures go to the
 * cloud cloud_pictures_storage() opens — any of the four once it is on the
 * release list there (step two of the plan, 26.09.2026).
 */

declare(strict_types=1);

require_once __DIR__ . '/photos.php';
require_once __DIR__ . '/cloud.php';

/** Is there anything to move photos INTO, on this server and for this user? */
function photo_cloud_ready(PDO $pdo, int $userId): bool
{
    return photos_ready($pdo)
        && column_exists($pdo, 'map_photos', 'cloud_fileid')
        && cloud_ready($pdo)
        && cloud_pictures_ready($pdo, $userId);
}

/**
 * The storage a picture lies in — by the provider on its row. Rows from
 * before anybody wrote the column can only be pCloud's, the one cloud
 * pictures went to then.
 */
function photo_cloud_storage(PDO $pdo, int $userId, array $photo): ?array
{
    $provider = (string) ($photo['cloud_provider'] ?? '') ?: 'pcloud';
    return cloud_storage_for($pdo, $userId, $provider);
}

/**
 * The name the picture gets over there.
 *
 * The name on this server is thirty-two random characters — right here,
 * where it must never collide and says nothing, and wrong in a folder a
 * person opens. What the phone called it is better, and the find day
 * before it makes the folder sort itself.
 */
function photo_cloud_name(array $photo): string
{
    $day = substr((string) ($photo['taken_at'] ?? $photo['created_at'] ?? ''), 0, 10);

    $own = trim((string) ($photo['original_name'] ?? ''));
    $own = preg_replace('/[\/\\\\:*?"<>|]+/u', ' ', $own) ?? '';
    $own = trim(preg_replace('/\s+/u', ' ', $own) ?? '');
    if ($own === '' || !preg_match('/\.(jpe?g|png|heic|webp)$/i', $own)) {
        $own = 'Beleg ' . (int) $photo['id'] . '.jpg';
    }

    return $day === '' ? $own : $day . ' ' . $own;
}

/**
 * Move one photo. Returns true when it now lies in the cloud.
 *
 * False is the ordinary answer, not an error: no cloud, no genus, the
 * provider unreachable. In every one of those the picture stays here and
 * nothing is lost — the next sweep tries again.
 */
function photo_to_cloud(PDO $pdo, int $userId, array $photo): bool
{
    if ((string) ($photo['cloud_fileid'] ?? '') !== '') {
        return true;
    }

    $path = photo_file($userId, (string) $photo['filename']);
    if ($path === null || !is_file($path)) {
        return false;
    }

    $s = cloud_pictures_storage($pdo, $userId);
    if ($s === null) {
        return false;
    }

    // THE RAW ROW, not point_load(): the species sit in extra_species, and the
    // prepared shape drops that field. Getting this wrong once made the whole
    // cloud button vanish — see api/v1/cloud.php.
    // BY ID ALONE since co-ownership (11.09.2026): the photo may hang on
    // somebody else's find — the leader's micrograph on a guest's point.
    // It still goes into the PHOTOGRAPHER's cloud (this user's), filed
    // under the find it shows; the find's name is the find's, whoever
    // holds it. With „AND user_id = ?" the push silently gave up and the
    // picture stayed on this server for good.
    $stmt = $pdo->prepare('SELECT * FROM map_points WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $photo['point_id']]);
    $point = $stmt->fetch();
    if ($point === false) {
        return false;
    }

    try {
        // Where the app writes at this storage: pCloud's and OneDrive's app
        // folder, the „YASAIMAPP" folder the app makes at Drive and S3.
        $root   = cloud_home_resolve($pdo, $userId, $s);
        $target = cloud_target_folder($s, $root, $pdo, $userId, $point);

        /* THE FILE IS READ WHOLE, and it may be: these are the shrunk
           2048 px pictures, half a megabyte apiece — the server takes
           nothing above 4 MB. For the originals the browser remains the
           right place (api/v1/cloud.php). */
        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException(t('Die Datei lässt sich nicht lesen: %s', basename($path)));
        }
        $name  = photo_cloud_name($photo);
        $taken = strtotime((string) ($photo['taken_at'] ?? '')) ?: null;
        $up = storage_upload_bytes($s, $target['folderid'], $bytes, $name, $taken, false,
            cloud_picture_type($name));
    } catch (RuntimeException $e) {
        error_log('photo_to_cloud (' . (int) $photo['id'] . '): ' . $e->getMessage());
        return false;
    }

    // Written down BEFORE the local file goes. Should the process die
    // between the two, a copy too many is lying about — which the sweep
    // clears up. The other order would lose the picture.
    $pdo->prepare(
        'UPDATE map_photos
            SET cloud_provider = ?, cloud_fileid = ?, cloud_folderid = ?,
                cloud_path = ?, cloud_uploaded_at = NOW()
          WHERE id = ? AND user_id = ?'
    )->execute([
        $s['provider'],
        (string) $up['fileid'],
        (string) $target['folderid'],
        mb_substr(implode('/', $target['path']), 0, 512),
        (int) $photo['id'],
        $userId,
    ]);
    // Its name as it was written (0151) — the find's naming takes it from
    // there (cloud_names.php), number and subject name included.
    if (cloud_names_ready($pdo)) {
        $pdo->prepare('UPDATE map_photos SET cloud_name = ? WHERE id = ?')
            ->execute([mb_substr((string) ($up['name'] ?? $name), 0, 255), (int) $photo['id']]);
        require_once __DIR__ . '/cloud_names.php';
        cloud_names_touch($pdo, (int) $photo['point_id']);
    }

    /* S3 DRAWS NO PREVIEW: the small one this server made at upload goes
       along into „.vorschau/320/" (s3_preview_key), before the local files
       go. The large view shows the picture itself — it IS the 2048 px one.
       Best effort: without a preview the tile loads the picture. */
    if ($s['provider'] === 's3') {
        photo_cloud_s3_preview($s, (string) $root, (string) $up['fileid'],
            photo_file($userId, (string) $photo['filename'], true));
    }

    photo_files_remove($userId, (string) $photo['filename']);

    /* A scale or measures set while the picture still waited here travel
       along the moment the picture moves — otherwise it would be the one
       case the sidecar hooks at the API never see. */
    $scale    = photo_scale_read($photo['scale'] ?? null);
    $measures = photo_measures_read($photo['measures'] ?? null);
    $title    = photo_title_clean($photo['title'] ?? null);
    $media    = photo_media_read($photo['media'] ?? null);
    $develop  = photo_develop_read($photo['develop'] ?? null);
    if ($scale !== null || $measures !== [] || $title !== null || $media !== null || $develop !== null) {
        measure_sidecar_store($pdo, $userId, $s['provider'], (string) $up['fileid'], $scale, $measures,
            $photo['width'] === null ? null : (int) $photo['width'],
            $photo['height'] === null ? null : (int) $photo['height'],
            $title, $photo['original_name'] ?? null, $media, $develop);
    }

    return true;
}

/**
 * Where a find's pictures belong — the path as the rows write it down, or
 * null for a find without a name.
 *
 * THE SAME PATH storage_find_folder() hands out and every row stores: above,
 * letter, below — never the two-letter compartment, which the counting at
 * the letter decides and which moves whole folders, not files. So a stored
 * path that equals this one lies where it belongs, whatever the compartments
 * did since, and the comparison needs no call to the provider.
 */
function cloud_find_path(PDO $pdo, int $userId, array $point): ?string
{
    // A point outside every species list lies in its list's branch (cloud_list_parts).
    $listed = cloud_list_parts($pdo, $point);
    if ($listed !== null) {
        return mb_substr(implode('/', $listed), 0, 512);
    }
    $find = cloud_find_from_point($pdo, $userId, $point);
    if ($find === null) {
        return null;
    }
    $parts = cloud_path_parts($find);
    if ($parts['letter'] === '') {
        return null;
    }
    return mb_substr(implode('/', array_merge($parts['above'], [$parts['letter']], $parts['below'])), 0, 512);
}

/**
 * Does a picture of this find lie somewhere else than the find now belongs?
 *
 * THE PICTURES FOLLOW THE FIND'S NAME (28.09.2026 — a find
 * mapped as „Lactarius spec." and determined under the microscope the next
 * day kept its photo in „Lactarius/Lactarius spec./"; nine vouchers of
 * named finds lay in such folders). Until then only „Unbestimmt" was left
 * again, once a name came. Now every change that gives the find another
 * folder — the species after „spec.", a corrected determination, another
 * find day or municipality — moves its pictures there.
 *
 * NEVER BACK INTO „UNBESTIMMT": a find that loses its name for a moment — a
 * title being retyped, a name not in any list — keeps its pictures where the
 * last name put them. „Unbestimmt" is for what never had one.
 */
function cloud_refile_due(PDO $pdo, int $userId, array $point, string $stored): bool
{
    $expected = cloud_find_path($pdo, $userId, $point);
    return $expected !== null && $expected !== $stored;
}

/**
 * Move one file of a find to where the find now belongs — the picture, its
 * sidecar in „Bearbeitung", and at S3 its preview.
 *
 * THE SAME SELF-REARRANGING the letter compartments do. Whoever determines
 * a find in December should not have to go into the storage and tidy up
 * after us; the folder scheme is only worth something if it holds without
 * anybody maintaining it.
 *
 * Only the file moves. Its id stays the same (but at S3, where the key is
 * the id), so everything pointing at it — the preview, the record, the link
 * into the folder — keeps working; the caller writes the new place down.
 * A refusal by the provider leaves the file where it is, and the next
 * opening of the find tries again.
 *
 * @return ?array{fileid: string, folderid: string, path: string}
 */
function cloud_file_refile(PDO $pdo, int $userId, array $s, array $point, string $fileid, string $what): ?array
{
    try {
        $root   = cloud_home_resolve($pdo, $userId, $s);
        $target = cloud_target_folder($s, $root, $pdo, $userId, $point);
        if (!empty($target['provisional'])) {
            return null;
        }
        /* THE SIDECAR MOVES ALONG (26.09.2026). It belongs with its
           picture; left behind it described nothing there. Looked for
           before the move, while the picture's name and folder are still
           the old ones — in „Bearbeitung" and, from before 10.11.2026,
           beside the picture (storage_sidecars_of). */
        $meta = storage_file_meta($s, $fileid);
        $sides = $meta === null ? [] : storage_sidecars_of($s, $meta['parent'], (string) $meta['name']);
        /* NEVER ONTO A FILE OF THE SAME NAME (04.10.2026). pCloud's move is
           renamefile, and renamefile REPLACES a namesake in the target
           without a word — a second camera's „P1010123.JPG" in the new
           folder would have been gone. Taken by another file: the picture
           stays where it is, and the log says why. */
        if ($meta !== null && (string) $meta['parent'] !== (string) $target['folderid']) {
            $there = storage_folder_files($s, $target['folderid'])[$meta['name']] ?? null;
            if ($there !== null && (string) $there['fileid'] !== (string) $fileid) {
                error_log('cloud_file_refile (' . $what . '): „' . $meta['name'] . '" is taken in the target folder — not moved');
                return null;
            }
        }
        $old = $fileid;
        $fileid = (string) storage_file_move($s, $fileid, $target['folderid']);
        if ($sides !== []) {
            try {
                /* The newest goes into the target's „Bearbeitung"; an old
                   one beside the picture, where a newer exists, is stale
                   and goes away rather than along. */
                $into = storage_sidecar_folder($s, $target['folderid'], true);
                /* A patch of „Entfernen" moves along like the sidecar
                   (storage_sidecars_of), but it is no newer SIDECAR. */
                $newer = array_values(array_filter($sides, static fn(array $x): bool => !$x['old'] && !isset($x['patch'])));
                foreach ($sides as $side) {
                    if ($side['old'] && $newer !== []) {
                        storage_file_delete($s, $side['fileid']);
                    } else {
                        storage_file_move($s, $side['fileid'], $into);
                    }
                }
            } catch (RuntimeException $e) {
                error_log('cloud_file_refile sidecar (' . $what . '): ' . $e->getMessage());
            }
        }
        // And at S3 the preview, whose key mirrors the picture's.
        if ($s['provider'] === 's3') {
            try {
                s3_move($s['token'], s3_preview_key((string) $root, $old, 320),
                    s3_preview_key((string) $root, $fileid, 320));
            } catch (RuntimeException $e) {
                error_log('cloud_file_refile preview (' . $what . '): ' . $e->getMessage());
            }
        }
        /* And the way it came from goes, once nothing lies along it any
           more — „Unbestimmt/<Tag Ort>" after the find got its name, the
           old genus after a new determination (storage_prune_empty). */
        $from = (string) ($meta['parent'] ?? '');
        if ($from !== '' && $from !== (string) $target['folderid']) {
            try {
                storage_prune_empty($pdo, $userId, $s, $from);
            } catch (RuntimeException $e) {
                error_log('cloud_file_refile prune (' . $what . '): ' . $e->getMessage());
            }
        }
    } catch (RuntimeException $e) {
        error_log('cloud_file_refile (' . $what . '): ' . $e->getMessage());
        return null;
    }

    return [
        'fileid'   => $fileid,
        'folderid' => (string) $target['folderid'],
        'path'     => mb_substr(implode('/', $target['path']), 0, 512),
    ];
}

/** A voucher photo in the cloud whose find now belongs elsewhere — move it. */
function photo_cloud_refile(PDO $pdo, int $userId, array $photo): bool
{
    $fileid = (string) ($photo['cloud_fileid'] ?? '');
    if ($fileid === '') {
        return false;
    }
    /* SINCE THE NAMES ARE KEPT (0151) the folder is the names' business
       too: cloud_names.php moves and renames in one step, guarded against
       namesakes, and two movers on one file would fight. The find is
       marked; the move comes from there. */
    if (cloud_names_ready($pdo)) {
        require_once __DIR__ . '/cloud_names.php';
        cloud_names_touch($pdo, (int) $photo['point_id']);
        return false;
    }

    $stmt = $pdo->prepare('SELECT * FROM map_points WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([(int) $photo['point_id'], $userId]);
    $point = $stmt->fetch();
    // Asked before the storage is opened: most pictures lie where they
    // belong, and opening a storage may cost a token refresh.
    if ($point === false || !cloud_refile_due($pdo, $userId, $point, (string) ($photo['cloud_path'] ?? ''))) {
        return false;
    }

    // Moved where it LIES — its own cloud, not the one connected now.
    $s = photo_cloud_storage($pdo, $userId, $photo);
    if ($s === null) {
        return false;
    }
    $moved = cloud_file_refile($pdo, $userId, $s, $point, $fileid, 'photo ' . (int) $photo['id']);
    if ($moved === null) {
        return false;
    }

    $pdo->prepare(
        'UPDATE map_photos SET cloud_fileid = ?, cloud_folderid = ?, cloud_path = ? WHERE id = ? AND user_id = ?'
    )->execute([$moved['fileid'], $moved['folderid'], $moved['path'], (int) $photo['id'], $userId]);

    return true;
}

/**
 * The recordings of a find (map_cloud_files, „Auswählen") follow its name
 * too — they never moved before, not even out of „Unbestimmt" (28.09.2026).
 * Only one's own: a recording on somebody else's find is filed by the
 * photographer's tree, and the point is not his to read here. BOUNDED like
 * the vouchers, for the same reason: this runs while the find is opened.
 *
 * @return int how many moved
 */
function recordings_refile(PDO $pdo, int $userId, int $pointId, int $atMost = 2): int
{
    if ($atMost < 1 || !table_exists($pdo, 'map_cloud_files')) {
        return 0;
    }
    // The names' business since 0151 — see photo_cloud_refile.
    if (cloud_names_ready($pdo)) {
        require_once __DIR__ . '/cloud_names.php';
        cloud_names_touch($pdo, $pointId);
        return 0;
    }
    $stmt = $pdo->prepare('SELECT * FROM map_points WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$pointId, $userId]);
    $point = $stmt->fetch();
    if ($point === false) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT id, provider, fileid, path FROM map_cloud_files
                            WHERE user_id = ? AND point_id = ? ORDER BY id');
    $stmt->execute([$userId, $pointId]);
    $storages = [];
    $moved = 0;
    foreach ($stmt->fetchAll() as $file) {
        if ((string) $file['fileid'] === '' || !cloud_refile_due($pdo, $userId, $point, (string) $file['path'])) {
            continue;
        }
        $provider = (string) ($file['provider'] ?? '') ?: 'pcloud';
        $s = $storages[$provider] ??= cloud_storage_for($pdo, $userId, $provider);
        if ($s === null) {
            continue;
        }
        $to = cloud_file_refile($pdo, $userId, $s, $point, (string) $file['fileid'], 'recording ' . (int) $file['id']);
        if ($to === null) {
            continue;
        }
        $pdo->prepare('UPDATE map_cloud_files SET fileid = ?, folderid = ?, path = ? WHERE id = ? AND user_id = ?')
            ->execute([$to['fileid'], $to['folderid'], $to['path'], (int) $file['id'], $userId]);
        if (++$moved >= $atMost) {
            break;
        }
    }
    return $moved;
}

/**
 * Move what is waiting.
 *
 * BOUNDED, and the bound is the point of it. This runs inside an ordinary
 * request — there is no cron on this hosting — so it may cost a moment, not
 * a minute. Whatever is left over goes on the next one; the pictures are
 * safe here in the meantime.
 *
 * @param int|null $pointId  only this find's, or all of them
 */
function photos_to_cloud(PDO $pdo, int $userId, ?int $pointId = null, int $atMost = 3): int
{
    if ($atMost < 1 || !photo_cloud_ready($pdo, $userId)) {
        return 0;
    }

    // Waiting here, and lying elsewhere than the find now belongs — one
    // query for both, because both are the same question: is this picture
    // where it belongs yet? For ONE find every picture is asked (the path
    // check is the database's, no call to the provider — photo_cloud_refile);
    // across all finds only the waiting ones and „Unbestimmt", as before.
    // Waiting ones first; the bound counts what really moved.
    $sql = 'SELECT * FROM map_photos WHERE user_id = ?'
         . ($pointId === null
             ? ' AND (cloud_fileid IS NULL OR cloud_path LIKE "Unbestimmt%" OR cloud_path LIKE "%/Unbestimmt/%")'
             : ' AND point_id = ?')
         . ' ORDER BY cloud_fileid IS NOT NULL, id'
         . ($pointId === null ? ' LIMIT ' . (int) $atMost : '');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($pointId === null ? [$userId] : [$userId, $pointId]);

    $moved = 0;
    foreach ($stmt->fetchAll() as $photo) {
        $done = (string) ($photo['cloud_fileid'] ?? '') !== ''
            ? photo_cloud_refile($pdo, $userId, $photo)
            : photo_to_cloud($pdo, $userId, $photo);
        if ($done && ++$moved >= $atMost) {
            break;
        }
    }

    // And whatever is left lying with nobody pointing at it. One readdir,
    // and it belongs exactly here: this is the only place that deliberately
    // separates a row from its file, so this is where the gap between the
    // two steps shows up.
    photos_orphans_remove($pdo, $userId);

    return $moved;
}

/**
 * The measuring data as a sidecar file WITH the picture in the cloud
 * (28.08.2026) — since 10.11.2026 in the subfolder
 * „Bearbeitung" of the picture's folder (SIDECAR_FOLDER): a safety copy, so a database crash could be
 * healed out of the users' own storages. Best effort like everything on
 * this bridge — a sidecar that could not be written is a missing copy,
 * not a lost original, and the next scale change writes it again.
 *
 * THE ACTUAL NAME COMES FROM THE PROVIDER (stat): renameifexists may have
 * called the picture „… (2).jpg" on the way up, and a sidecar named
 * after our guess would sit beside the WRONG file for a human eye.
 *
 * A picture with NEITHER scale NOR measures loses its sidecar — a
 * tombstone JSON would clutter folders with files that say "nothing".
 *
 * AT THE PICTURE'S OWN CLOUD — $provider is where it lies (26.09.2026),
 * and the seam speaks to it: a sidecar beside a Drive picture goes into
 * that Drive folder, beside an S3 picture under its prefix.
 *
 * @param string $provider  where the picture lies — pcloud, gdrive, onedrive, s3
 * @param string $fileid    the picture's id at the provider
 * @param ?array $scale     the CLEAN shape (photo_scale_read), or null
 * @param array  $measures  the CLEAN list (photo_measures_read), [] = none
 */
function measure_sidecar_store(PDO $pdo, int $userId, string $provider, string $fileid,
                               ?array $scale, array $measures,
                               ?int $width, ?int $height,
                               ?string $title = null, ?string $original = null,
                               ?array $media = null, ?array $develop = null): void
{
    if ($fileid === '') {
        return;
    }
    /* THE COPY NEVER FAILS THE SAVE (09.11.2026, after the maintainer kept
       getting „Die Bearbeitung ließ sich nicht speichern"): what the user
       did is in the database before this runs — the sidecar is only its
       copy beside the picture. Opening the cloud stood outside every catch,
       and the write caught RuntimeException alone, so an expired login or
       any other kind of failure there turned a stored change into an error
       answer. Whatever goes wrong here is logged and the answer stays good;
       the next change of the picture writes the copy again. */
    try {
        $s = cloud_storage_for($pdo, $userId, $provider);
        if ($s === null) {
            return;
        }
        $written = measure_sidecar_write($s, $fileid, $scale, $measures, $width, $height, $title, $original, $media, $develop);
        if ($written !== null) {
            measure_sidecar_settle($pdo, $userId, $s, $fileid, $written);
        }
    } catch (Throwable $e) {
        error_log('measure_sidecar_store (' . $provider . ' ' . $fileid . '): ' . get_class($e) . ': ' . $e->getMessage());
    }
}

/**
 * WAS THE PICTURE RENAMED WHILE ITS SIDECAR WAS ON THE WAY? Then the
 * sidecar just written lies under a name the picture no longer has — and
 * is put right here.
 *
 * THE GAP (22.11.2026, 203 left-over sidecars in the maintainer's pCloud).
 * measure_sidecar_write() asks the provider for the picture's name and
 * uploads under it; between the two the renaming (cloud_names.php) may
 * give the picture its derived name — it runs right after the answer of
 * the very upload that brought the picture, at the moment the microscope
 * saves its scale. The renaming looked for the old sidecar before this one
 * existed; cloud_names_sweep() catches what lands before its end, this
 * function what lands after.
 *
 * THE DATABASE SAYS IT FOR NOTHING. The renaming writes the new name on
 * the row the moment the picture has it, so one SELECT tells whether
 * anything happened — a second question to the provider on every save of
 * a measurement would be a quarter more waiting for a case that passes.
 * Only when the row's name is not the one written under is the provider
 * asked again, and only when THAT name differs too is something done:
 *
 *   - the file under the old name goes — as a sidecar it describes nothing;
 *   - WHOEVER HOLDS THAT NAME NOW gets its own sidecar back. Numbers shift
 *     when a picture is added: „20 Sporen" becomes „21 Sporen" and its
 *     neighbour takes „20 Sporen" — the late upload then REPLACED that
 *     neighbour's sidecar with this picture's measures (one picture, one
 *     sidecar: the upload overwrites its namesake);
 *   - this picture's sidecar is written again, under the name it has now.
 *     Once: a picture renamed a second time within that second is caught
 *     by the renaming's own sweep.
 *
 * Compared through cloud_name_key(): a row says „ü" in one spelling and an
 * older file up there carries the other — that is no renaming.
 *
 * @param array{name: string, folder: string, sidecar: string, again?: callable} $written
 */
function measure_sidecar_settle(PDO $pdo, int $userId, array $s, string $fileid, array $written): void
{
    $now = null;
    foreach ([['map_cloud_files', 'fileid', 'name'], ['map_photos', 'cloud_fileid', 'cloud_name']] as [$table, $idColumn, $nameColumn]) {
        if (!table_exists($pdo, $table) || !column_exists($pdo, $table, $nameColumn)) {
            continue;
        }
        $q = $pdo->prepare("SELECT $nameColumn FROM $table WHERE user_id = ? AND $idColumn = ? LIMIT 1");
        $q->execute([$userId, $fileid]);
        $found = $q->fetchColumn();
        if (is_string($found) && $found !== '') {
            $now = $found;
            break;
        }
    }
    if ($now === null || cloud_name_key($now) === cloud_name_key($written['name'])) {
        return;   // the usual case: nothing moved
    }
    $meta = storage_file_meta($s, $fileid);
    if ($meta !== null && cloud_name_key((string) $meta['name']) === cloud_name_key($written['name'])
        && (string) $meta['parent'] === $written['folder']) {
        return;   // row and cloud merely disagree (a file renamed by hand) — the sidecar lies right
    }

    storage_file_delete($s, $written['sidecar']);

    // Whoever holds the old name now, in the old folder: its sidecar from its own row.
    require_once __DIR__ . '/cloud_names.php';
    foreach ([['cloud', 'map_cloud_files', 'fileid', 'folderid', 'name'],
              ['photo', 'map_photos', 'cloud_fileid', 'cloud_folderid', 'cloud_name']] as [$kind, $table, $idColumn, $folderColumn, $nameColumn]) {
        if (!table_exists($pdo, $table) || !column_exists($pdo, $table, $nameColumn)) {
            continue;
        }
        $q = $pdo->prepare("SELECT * FROM $table WHERE user_id = ? AND $folderColumn = ? AND $nameColumn = ? AND $idColumn <> ? LIMIT 1");
        $q->execute([$userId, $written['folder'], $written['name'], $fileid]);
        $holder = $q->fetch();
        // The database compares names loosely (an accent more or less is the same to it) — the key decides.
        if ($holder !== false && (string) ($holder[$idColumn] ?? '') !== ''
            && cloud_name_key((string) $holder[$nameColumn]) === cloud_name_key($written['name'])) {
            cloud_names_sidecar($pdo, $s, ['kind' => $kind, 'row' => $holder], (string) $holder[$idColumn]);
        }
    }

    if ($meta !== null && isset($written['again'])) {
        ($written['again'])();
    }
}

/**
 * The same, at a storage already open — the renaming (cloud_names.php)
 * holds one for the whole find and need not open it once per picture.
 *
 * @return ?array{name: string, folder: string, sidecar: string, again: callable}
 *         what was written: the picture's name and folder as the provider
 *         named them, the sidecar's id, and the same write once more — for
 *         measure_sidecar_settle(), which checks whether the picture was
 *         renamed meanwhile. Null where nothing was written.
 */
function measure_sidecar_write(array $s, string $fileid, ?array $scale, array $measures,
                               ?int $width, ?int $height, ?string $title = null, ?string $original = null,
                               ?array $media = null, ?array $develop = null): ?array
{
    $provider = (string) $s['provider'];
    try {
        $meta     = storage_file_meta($s, $fileid);
        $name     = (string) ($meta['name'] ?? '');
        $folderId = (string) ($meta['parent'] ?? '');
        // A picture never lies in the very top (pCloud's 0): no sidecar there.
        if ($name === '' || $folderId === '' || $folderId === '0') {
            return null;
        }

        if ($scale === null && $measures === [] && $title === null && $media === null && $develop === null) {
            // Wherever it lies — an old one beside the picture too (storage_sidecar_delete).
            storage_sidecar_delete($s, $folderId, $name);
            return null;
        }

        /* INTO „BEARBEITUNG" (10.11.2026; cloud.php,
           SIDECAR_FOLDER). An old copy beside the picture is left for the
           one-time job, which finds this newer one and drops the old
           (sidecar_move.php) — looking for it here would cost every save a
           listing for a case that passes. */
        $up = storage_upload_bytes(
            $s, storage_sidecar_folder($s, $folderId, true),
            measure_sidecar_json($scale, $measures, $name, $width, $height, $title, $original, $media, $develop),
            measure_sidecar_name($name),
            null,
            true,   // replace: one picture, one sidecar — re-calibrating heals it
            'application/json'
        );
        return [
            'name'    => $name,
            'folder'  => $folderId,
            'sidecar' => (string) $up['fileid'],
            'again'   => static fn(): ?array => measure_sidecar_write($s, $fileid, $scale, $measures,
                $width, $height, $title, $original, $media, $develop),
        ];
    } catch (Throwable $e) {
        // Any failure, not only the provider's own (measure_sidecar_store says why).
        error_log('measure_sidecar_store (' . $provider . ' ' . $fileid . '): ' . get_class($e) . ': ' . $e->getMessage());
        return null;
    }
}

/** The sidecar for a VOUCHER photo, off its row. Quietly nothing while
 *  the picture still waits on this server — photo_to_cloud() writes the
 *  sidecar along the moment it moves. */
function photo_scale_sidecar(PDO $pdo, int $userId, array $photo): void
{
    measure_sidecar_store($pdo, $userId,
        (string) ($photo['cloud_provider'] ?? '') ?: 'pcloud',
        (string) ($photo['cloud_fileid'] ?? ''),
        photo_scale_read($photo['scale'] ?? null),
        photo_measures_read($photo['measures'] ?? null),
        $photo['width'] === null ? null : (int) $photo['width'],
        $photo['height'] === null ? null : (int) $photo['height'],
        photo_title_clean($photo['title'] ?? null),
        $photo['original_name'] ?? null,
        photo_media_read($photo['media'] ?? null),
        photo_develop_read($photo['develop'] ?? null));
}

/** How many pictures of this user still lie on this server. */
function photos_waiting(PDO $pdo, int $userId): int
{
    if (!photos_ready($pdo) || !column_exists($pdo, 'map_photos', 'cloud_fileid')) {
        return 0;
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM map_photos WHERE user_id = ? AND cloud_fileid IS NULL'
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Deliver a voucher photo that lies in the user's storage.
 *
 * THE PREVIEW COMES FROM THE PROVIDER'S RENDERING, the large one from the
 * file itself. That is not a shortcut: what went up there IS the 2048 px
 * picture — the phone shrinks before anything leaves it (see photos.js) —
 * so getfilelink hands back exactly what photo.php used to read off the
 * disk. Only the small one is worth having rendered, and pCloud does that
 * better than a second copy on our side ever would.
 *
 * Answers by itself, including the failure: 502 when the provider has the
 * picture but will not part with it, so that "gone" and "not right now"
 * do not look the same.
 */
function photo_from_cloud(PDO $pdo, int $userId, array $photo, bool $small): void
{
    $s = photo_cloud_storage($pdo, $userId, $photo);
    if ($s === null) {
        http_response_code(404);
        return;
    }

    try {
        // S3's previews hang off the app's prefix; nobody else needs it.
        $home = $s['provider'] === 's3' ? (string) cloud_home_resolve($pdo, $userId, $s) : null;
        $candidates = storage_file_fetch($s, (string) $photo['cloud_fileid'],
            $small ? PHOTO_PREVIEW_EDGE : null, $home);
    } catch (RuntimeException $e) {
        error_log('photo_from_cloud (' . (int) $photo['id'] . '): ' . $e->getMessage());
        $candidates = [];
    }

    if ($candidates === []) {
        http_response_code(502);
        return;
    }
    if (!cloud_deliver($candidates, 'photo ' . (int) $photo['id'])) {
        http_response_code(502);
    }
}

/**
 * Lay a picture's small preview into S3's „.vorschau/320/" — S3 draws
 * none of its own (s3_preview_key). Best effort: without one, the tile
 * loads the picture itself (storage_file_fetch names it second).
 */
function photo_cloud_s3_preview(array $s, string $home, string $key, ?string $previewPath): void
{
    if ($previewPath === null || !is_file($previewPath)) {
        return;
    }
    $bytes = @file_get_contents($previewPath);
    if ($bytes === false || $bytes === '') {
        return;
    }
    $at    = s3_preview_key($home, $key, 320);
    $slash = strrpos($at, '/');
    try {
        s3_upload_bytes($s['token'], substr($at, 0, $slash + 1), $bytes, substr($at, $slash + 1),
            null, true, 'image/jpeg');
    } catch (RuntimeException $e) {
        error_log('photo_cloud_s3_preview (' . $key . '): ' . $e->getMessage());
    }
}

