<?php
/**
 * The copy worker — the complete picture set of a find in every co-owner's
 * cloud (migration 0098).
 *
 * 11.09.2026: „alle Bilder von allen Bearbeitern immer auch
 * gleich in die eigene Cloud mitgespielt, sodass ein kompletter Fundsatz
 * in jeder Cloud ist mit allen Bildern, die es dazu gibt, auch fremde
 * Bilder." And: „wer keine eigene Cloud hat, bekommt auch keine Kopie" —
 * the server never holds a copy; a copy exists only from cloud to cloud.
 *
 * ONE PICTURE, MANY STORAGE PLACES. Title, scale and measures live once,
 * on the origin row (map_cloud_files today, map_photos from step 2 on);
 * this worker only moves BYTES. Two rows saying different things about
 * one picture was the tangle the plan avoided from the start.
 *
 * THE SAME LOOP FOR THE TAB AND FOR CRON (cronjobs/hourly.php): a
 * chunk button in the administration and an unattended twin call
 * cloud_copies_run(), exactly like the elevations and the municipalities.
 * Every run reads its progress from the rows themselves — status,
 * attempts, tried_at — so a run may stop anywhere and the next continues.
 *
 * BYTES GO THROUGH THIS SERVER, and that is new: until now the server only
 * handed out upload targets and thumbnail links, never a file. Here it
 * reads from the photographer's cloud (getfilelink) and writes into the
 * recipient's (uploadfile) with the two stored secrets — and only through
 * the helpers of cloud.php, so the secret keeps the one path it has.
 *
 * WHO CALLS THE ENQUEUE HELPERS: point_photo_attached() in
 * includes/point_owners.php — from photo_create() and from cloud.php's
 * record, the two places a picture arrives on a find (step 2 of the plan).
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';
// photo_file() and photo_cloud_name(): a voucher photo's bytes and the
// name it carries into a cloud — the same name its own push uses.
require_once __DIR__ . '/photos.php';
require_once __DIR__ . '/photo_cloud.php';

/** One chunk from the tab; cron brings its own budget. */
const CLOUD_COPIES_SECONDS = 25;
/** Air between two transfers — the clouds belong to someone else. */
const CLOUD_COPIES_PAUSE_MS = 300;
/** After this many failed attempts a copy is given up and shown, not retried. */
const CLOUD_COPIES_MAX_ATTEMPTS = 8;
/** Larger files are given up at once: file_get_contents holds the whole
 *  picture in memory, and a shrunken micro photo is a few hundred kB, a
 *  macro original ten MB — sixty-four is far above both. */
const CLOUD_COPIES_MAX_BYTES = 64 * 1024 * 1024;

/** Migration 0098 — the code goes live before it. */
function cloud_copies_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_cloud_copies') && table_exists($pdo, 'map_point_owners');
}

/**
 * Who is to receive a copy of this find's pictures: the holder and every
 * co-owner who is not in his trash — and of those only the ones WITH a
 * cloud that takes pictures (CLOUD_PICTURE_PROVIDERS — pCloud alone until
 * 26.09.2026, every released one since). Guests have no account and
 * therefore never appear; a user without a cloud is a co-owner all the
 * same, just without a copy (the backfill on connecting a cloud is step 5).
 *
 * @return int[] user ids
 */
function cloud_copies_recipients(PDO $pdo, int $pointId): array
{
    $released = CLOUD_PICTURE_PROVIDERS;
    $stmt = $pdo->prepare(
        'SELECT DISTINCT u.user_id
           FROM (SELECT user_id FROM map_points WHERE id = ?
                 UNION
                 SELECT user_id FROM map_point_owners WHERE point_id = ? AND deleted_at IS NULL) u
           JOIN map_cloud_accounts a ON a.user_id = u.user_id AND a.secret IS NOT NULL
                AND a.provider IN (' . implode(', ', array_fill(0, count($released), '?')) . ')'
    );
    $stmt->execute(array_merge([$pointId, $pointId], $released));
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * A cloud recording arrived on a find: every other recipient gets a row.
 * INSERT IGNORE on the unique key — enqueueing twice is harmless, and a
 * copy that already exists (or is under way) is left alone.
 *
 * @return int rows newly queued
 */
function cloud_copies_enqueue_file(PDO $pdo, int $fileId): int
{
    if (!cloud_copies_ready($pdo)) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT user_id, point_id FROM map_cloud_files WHERE id = ?');
    $stmt->execute([$fileId]);
    $origin = $stmt->fetch();
    if ($origin === false) {
        return 0;
    }
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO map_cloud_copies (origin_kind, origin_id, user_id) VALUES ("cloud", ?, ?)'
    );
    $queued = 0;
    foreach (cloud_copies_recipients($pdo, (int) $origin['point_id']) as $userId) {
        if ($userId === (int) $origin['user_id']) {
            continue;   // his own storage is the origin row itself
        }
        $insert->execute([$fileId, $userId]);
        $queued += $insert->rowCount();
    }
    return $queued;
}

/**
 * A voucher photo arrived on a find (photo_create): the same as above for
 * the server-side kind. „Auch das Belegfoto" — the copy set is complete
 * only with it.
 *
 * @return int rows newly queued
 */
function cloud_copies_enqueue_photo(PDO $pdo, int $photoId): int
{
    if (!cloud_copies_ready($pdo)) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT user_id, point_id FROM map_photos WHERE id = ?');
    $stmt->execute([$photoId]);
    $origin = $stmt->fetch();
    if ($origin === false) {
        return 0;
    }
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO map_cloud_copies (origin_kind, origin_id, user_id) VALUES ("photo", ?, ?)'
    );
    $queued = 0;
    foreach (cloud_copies_recipients($pdo, (int) $origin['point_id']) as $userId) {
        if ($userId === (int) $origin['user_id']) {
            continue;
        }
        $insert->execute([$photoId, $userId]);
        $queued += $insert->rowCount();
    }
    return $queued;
}

/**
 * Somebody became a co-owner of a find (or connected a cloud, step 5):
 * every picture of the find that is not his gets a row for him — cloud
 * recordings and voucher photos alike.
 *
 * @return int rows newly queued
 */
function cloud_copies_enqueue_point(PDO $pdo, int $pointId, int $userId): int
{
    if (!cloud_copies_ready($pdo)) {
        return 0;
    }
    // No cloud that takes pictures, no copy — the rule.
    $released = CLOUD_PICTURE_PROVIDERS;
    $has = $pdo->prepare('SELECT 1 FROM map_cloud_accounts WHERE user_id = ? AND secret IS NOT NULL
                           AND provider IN (' . implode(', ', array_fill(0, count($released), '?')) . ') LIMIT 1');
    $has->execute(array_merge([$userId], $released));
    if ($has->fetchColumn() === false) {
        return 0;
    }
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO map_cloud_copies (origin_kind, origin_id, user_id) VALUES (?, ?, ?)'
    );
    $queued = 0;
    foreach (['cloud' => 'map_cloud_files', 'photo' => 'map_photos'] as $kind => $table) {
        $stmt = $pdo->prepare("SELECT id FROM $table WHERE point_id = ? AND user_id <> ?");
        $stmt->execute([$pointId, $userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $originId) {
            $insert->execute([$kind, (int) $originId, $userId]);
            $queued += $insert->rowCount();
        }
    }
    return $queued;
}

/**
 * The origin is gone: its copies are to be removed. The rows stay until
 * the worker has deleted the files — a row is the memory that a file
 * still lies somewhere.
 *
 * @return int rows marked
 */
function cloud_copies_retire(PDO $pdo, string $kind, int $originId): int
{
    if (!cloud_copies_ready($pdo)) {
        return 0;
    }
    $stmt = $pdo->prepare(
        'UPDATE map_cloud_copies SET status = "deleting", attempts = 0, tried_at = NULL, error = NULL
          WHERE origin_kind = ? AND origin_id = ? AND status IN ("waiting", "copied", "given_up")'
    );
    $stmt->execute([$kind, $originId]);
    // Never copied at all: nothing to remove, the row can go at once.
    $pdo->prepare(
        'DELETE FROM map_cloud_copies WHERE origin_kind = ? AND origin_id = ? AND status = "deleting" AND fileid IS NULL'
    )->execute([$kind, $originId]);
    return $stmt->rowCount();
}

/**
 * THE BACKFILL (step 5, 11.09.2026: „wenn sich jemand, der
 * keine Cloud hat, eine Cloud einrichtet, müsste geprüft werden, ob er
 * irgendwo gemeinsame Bearbeitungen hat und entsprechend kopiert
 * werden"). Every find in his circle — held or co-owned — that carries
 * somebody else's pictures gets those queued for him. Set-based first,
 * so an account with thousands of finds and three shared ones costs
 * one query and three small ones, not thousands.
 *
 * Called when a cloud is connected (cloud.php) and when a guest's rows
 * are tied to an account (excursion_claim). Idempotent like everything
 * in this queue: INSERT IGNORE on the unique key.
 *
 * @return int rows newly queued
 */
function cloud_copies_backfill_user(PDO $pdo, int $userId): int
{
    if (!cloud_copies_ready($pdo)) {
        return 0;
    }
    $stmt = $pdo->prepare(
        'SELECT DISTINCT p.id
           FROM map_points p
           LEFT JOIN map_point_owners o ON o.point_id = p.id AND o.user_id = ? AND o.deleted_at IS NULL
          WHERE p.deleted_at IS NULL
            AND (p.user_id = ? OR o.user_id IS NOT NULL)
            AND (EXISTS (SELECT 1 FROM map_cloud_files f WHERE f.point_id = p.id AND f.user_id <> ?)
              OR EXISTS (SELECT 1 FROM map_photos ph WHERE ph.point_id = p.id AND ph.user_id <> ?))'
    );
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $queued = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $pointId) {
        $queued += cloud_copies_enqueue_point($pdo, (int) $pointId, $userId);
    }
    return $queued;
}

/**
 * ONE USER lets go of a find (leaving it for good): his copies of the
 * others' pictures there are to be removed from HIS cloud. The mirror of
 * cloud_copies_retire(), which removes one picture's copies everywhere.
 *
 * @return int rows marked or dropped
 */
function cloud_copies_release(PDO $pdo, int $userId, int $pointId): int
{
    if (!cloud_copies_ready($pdo)) {
        return 0;
    }
    $where = '(origin_kind = "cloud" AND origin_id IN (SELECT id FROM map_cloud_files WHERE point_id = ?))
           OR (origin_kind = "photo" AND origin_id IN (SELECT id FROM map_photos WHERE point_id = ?))';
    $gone = $pdo->prepare("DELETE FROM map_cloud_copies WHERE user_id = ? AND fileid IS NULL AND ($where)");
    $gone->execute([$userId, $pointId, $pointId]);
    $mark = $pdo->prepare(
        "UPDATE map_cloud_copies SET status = 'deleting', attempts = 0, tried_at = NULL, error = NULL
          WHERE user_id = ? AND status IN ('waiting', 'copied', 'given_up') AND ($where)"
    );
    $mark->execute([$userId, $pointId, $pointId]);
    return $gone->rowCount() + $mark->rowCount();
}

/** What still waits — the number behind the tab and the cron's last word. */
function cloud_copies_pending(PDO $pdo): int
{
    if (!cloud_copies_ready($pdo)) {
        return 0;
    }
    return (int) $pdo->query(
        'SELECT COUNT(*) FROM map_cloud_copies WHERE status IN ("waiting", "deleting")'
    )->fetchColumn();
}

/**
 * The rows due now. BACK-OFF FROM THE ROW ITSELF: after n failed attempts
 * a row waits 2^n minutes, a day at most — a full cloud does not become
 * emptier by being asked every thirty seconds, and a cloud that is merely
 * down for lunch gets its chance back within the hour.
 */
function cloud_copies_due(PDO $pdo, int $limit): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM map_cloud_copies
          WHERE status IN ("waiting", "deleting")
            AND (tried_at IS NULL
                 OR tried_at < DATE_SUB(NOW(), INTERVAL LEAST(POW(2, attempts), 1440) MINUTE))
          ORDER BY tried_at IS NULL DESC, tried_at, id
          LIMIT ' . max(1, $limit)
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

/** The bytes of a file in the photographer's cloud, fetched here — the
 *  link is bound to the asking address (pCloud), or carries a token or a
 *  signature the browser must not see; the same reason cloud_thumb.php
 *  cannot hand it on. AT THE CLOUD IT LIES IN (26.09.2026) — the provider
 *  on the origin's row, whichever of the four.
 *
 *  READ WITH A CEILING (security review, 23.09.2026): the size on the
 *  origin row is what the client said when recording it; a multi-GB file
 *  sent as „size 1" was once read whole — a fatal out-of-memory nobody
 *  can catch. storage_file_bytes() stops one byte past the limit. */
function cloud_copies_fetch(PDO $pdo, int $ownerId, string $provider, string $fileid): string
{
    $from = cloud_storage_for($pdo, $ownerId, $provider);
    if ($from === null) {
        throw new RuntimeException(t('Die Cloud des Fotografen ist nicht verbunden.'));
    }
    return storage_file_bytes($from, $fileid, CLOUD_COPIES_MAX_BYTES);
}

/**
 * The real transfer: photographer's cloud → recipient's cloud, into the
 * recipient's own find folder (the same folder tree cloud_target_folder()
 * builds for his own uploads — a copy lies where he would have put the
 * picture himself).
 *
 * @return array{provider: string, fileid: string, folderid: string, path: string, size: int}
 */
/**
 * The bytes and the name of an origin, whatever its kind. A cloud
 * recording lies in the photographer's cloud. A voucher photo lies on
 * this server until its own push moved it into his cloud (photo_to_cloud
 * removes the local file afterwards) — so it is read from disk while it
 * is there and from his cloud once it is not. The name is the one the
 * push gives it, so a copy is called what the original is called.
 *
 * @return array{bytes: string, name: string}
 */
function cloud_copies_origin_bytes(PDO $pdo, array $copy, array $origin): array
{
    if ((int) ($origin['size'] ?? $origin['bytes'] ?? 0) > CLOUD_COPIES_MAX_BYTES) {
        throw new RuntimeException(t('Die Datei ist zu groß zum Kopieren (%s).',
            cloud_size((int) ($origin['size'] ?? $origin['bytes']))));
    }
    if ($copy['origin_kind'] === 'cloud') {
        return ['bytes' => cloud_copies_fetch($pdo, (int) $origin['user_id'],
                    (string) ($origin['provider'] ?? '') ?: 'pcloud', (string) $origin['fileid']),
                'name'  => (string) $origin['name']];
    }
    $name = photo_cloud_name($origin);
    $path = photo_file((int) $origin['user_id'], (string) $origin['filename']);
    if ($path !== null && is_file($path)) {
        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException(t('Die Datei ließ sich nicht aus der Cloud des Fotografen laden.'));
        }
        return ['bytes' => $bytes, 'name' => $name];
    }
    if ((string) ($origin['cloud_fileid'] ?? '') !== '') {
        return ['bytes' => cloud_copies_fetch($pdo, (int) $origin['user_id'],
                    (string) ($origin['cloud_provider'] ?? '') ?: 'pcloud', (string) $origin['cloud_fileid']),
                'name'  => $name];
    }
    throw new RuntimeException(t('Die Datei ließ sich nicht aus der Cloud des Fotografen laden.'));
}

function cloud_copies_transfer_pcloud(PDO $pdo, array $copy, array $origin, array $point): array
{
    /* INTO THE CLOUD THE RECIPIENT'S PICTURES GO TO (cloud_pictures_storage),
       whichever of the released ones — the origin may lie at another
       provider altogether; the bytes pass through here either way. At S3
       the copy gets no preview of its own: the tile loads the picture
       itself until someone draws one (storage_file_fetch names it
       second). */
    $to = cloud_pictures_storage($pdo, (int) $copy['user_id']);
    if ($to === null) {
        throw new RuntimeException(t('Die Cloud des Empfängers ist nicht verbunden.'));
    }
    ['bytes' => $bytes, 'name' => $name] = cloud_copies_origin_bytes($pdo, $copy, $origin);
    if (strlen($bytes) > CLOUD_COPIES_MAX_BYTES) {
        throw new RuntimeException(t('Die Datei ist zu groß zum Kopieren (%s).', cloud_size(strlen($bytes))));
    }

    $folder = cloud_target_folder($to, cloud_home_resolve($pdo, (int) $copy['user_id'], $to),
        $pdo, (int) $copy['user_id'], $point);
    $up = storage_upload_bytes($to, $folder['folderid'], $bytes, $name, null, false, cloud_picture_type($name));

    return [
        'provider' => $to['provider'],
        'fileid'   => (string) $up['fileid'],
        'folderid' => (string) $folder['folderid'],
        'path'     => implode('/', (array) ($folder['path'] ?? [])),
        'size'     => strlen($bytes),
        // As the provider wrote it — „… (2)" if the name was taken.
        'name'     => (string) ($up['name'] ?? $name),
    ];
}

/**
 * Remove one copy from the recipient's cloud — the cloud it LIES in, by
 * the provider on the copy's row (26.09.2026). A file already gone counts
 * as removed: somebody tidied his cloud by hand. Done, then. The name
 * stays for the worker's default and the tests' substitute.
 */
function cloud_copies_delete_pcloud(PDO $pdo, array $copy): void
{
    $to = cloud_storage_for($pdo, (int) $copy['user_id'], (string) ($copy['provider'] ?? '') ?: 'pcloud');
    if ($to === null) {
        throw new RuntimeException(t('Die Cloud des Empfängers ist nicht verbunden.'));
    }
    storage_picture_delete($pdo, (int) $copy['user_id'], $to, (string) $copy['fileid']);
}

/**
 * Work through due rows until the deadline — the shared core of the tab's
 * chunk button and the cron job.
 *
 * $transfer and $delete are injectable for the test: the state machine —
 * attempts, back-off, giving up, retiring — is what must be right, and it
 * has to be provable without a cloud on the machine. The real ones are
 * the pCloud functions above.
 *
 * @return array{copied: int, removed: int, failed: int, given_up: int}
 */
function cloud_copies_run(PDO $pdo, float $deadline, ?callable $transfer = null, ?callable $delete = null): array
{
    $copied = 0; $removed = 0; $failed = 0; $givenUp = 0;
    if (!cloud_copies_ready($pdo)) {
        return compact('copied', 'removed', 'failed') + ['given_up' => $givenUp];
    }
    $transfer ??= 'cloud_copies_transfer_pcloud';
    $delete   ??= 'cloud_copies_delete_pcloud';

    /* The copy's provider is written with it (26.09.2026): it is read
       back when the copy is shown or deleted, and „wherever the account
       stores now" would be wrong the day it stores somewhere else. */
    $done = $pdo->prepare(
        'UPDATE map_cloud_copies
            SET status = "copied", provider = ?, fileid = ?, folderid = ?, path = ?, size = ?, copied_at = NOW(),
                tried_at = NOW(), error = NULL
          WHERE id = ?'
    );
    /* THE ATTEMPT IS COUNTED BEFORE IT IS MADE (security review,
       23.09.2026). Counted in the catch, a crash that no catch sees — PHP
       out of memory on a huge file — left the row untouched, and since
       untouched rows come first, every later run died on the same row:
       one bad file stalled the queue for everybody. Now the row carries
       its attempt and time before the transfer; a crash is a failed
       attempt like any other, and the back-off moves on past it. */
    $attempt = $pdo->prepare(
        'UPDATE map_cloud_copies
            SET attempts = attempts + 1, tried_at = NOW(),
                status = IF(attempts + 1 >= ?, "given_up", status)
          WHERE id = ?'
    );
    $miss = $pdo->prepare('UPDATE map_cloud_copies SET error = ? WHERE id = ?');
    $gone = $pdo->prepare('DELETE FROM map_cloud_copies WHERE id = ?');

    foreach (cloud_copies_due($pdo, 200) as $copy) {
        if (microtime(true) >= $deadline) {
            break;
        }
        // A delete keeps its status: given_up is only for copies still owed.
        if ($copy['status'] === 'deleting') {
            $pdo->prepare('UPDATE map_cloud_copies SET attempts = attempts + 1, tried_at = NOW() WHERE id = ?')
                ->execute([$copy['id']]);
        } else {
            $attempt->execute([CLOUD_COPIES_MAX_ATTEMPTS, $copy['id']]);
        }
        try {
            if ($copy['status'] === 'deleting') {
                if ($copy['fileid'] !== null) {
                    $delete($pdo, $copy);
                }
                $gone->execute([$copy['id']]);
                $removed++;
            } else {
                $table = match ($copy['origin_kind']) {
                    'cloud' => 'map_cloud_files',
                    'photo' => 'map_photos',
                    // A kind nobody wrote a reader for must not spin for ever.
                    default => throw new RuntimeException(t('Unbekannte Herkunft: %s', (string) $copy['origin_kind'])),
                };
                $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
                $stmt->execute([$copy['origin_id']]);
                $origin = $stmt->fetch();
                if ($origin === false) {
                    // The origin vanished without retiring its copies —
                    // nothing to copy any more, and nothing to remove.
                    $gone->execute([$copy['id']]);
                    continue;
                }
                $stmt = $pdo->prepare('SELECT * FROM map_points WHERE id = ?');
                $stmt->execute([$origin['point_id']]);
                $point = $stmt->fetch();
                if ($point === false) {
                    $gone->execute([$copy['id']]);
                    continue;
                }
                $result = $transfer($pdo, $copy, $origin, $point);
                $done->execute([(string) ($result['provider'] ?? 'pcloud'),
                    (string) $result['fileid'], (string) $result['folderid'],
                    mb_substr((string) $result['path'], 0, 512), $result['size'], $copy['id']]);
                /* The copy's name (0151), and its find due: a copy is named
                   like its origin, and the origin may have been renamed
                   since the bytes left (cloud_names.php). */
                if (isset($result['name']) && column_exists($pdo, 'map_cloud_copies', 'name')) {
                    $pdo->prepare('UPDATE map_cloud_copies SET name = ? WHERE id = ?')
                        ->execute([mb_substr((string) $result['name'], 0, 255), $copy['id']]);
                }
                require_once __DIR__ . '/cloud_names.php';
                cloud_names_touch($pdo, (int) $point['id']);
                $copied++;
            }
        } catch (Throwable $e) {
            $miss->execute([mb_substr($e->getMessage(), 0, 255), $copy['id']]);
            if ((int) $copy['attempts'] + 1 >= CLOUD_COPIES_MAX_ATTEMPTS) {
                $givenUp++;
            } else {
                $failed++;
            }
        }
        usleep(CLOUD_COPIES_PAUSE_MS * 1000);
    }

    return compact('copied', 'removed', 'failed') + ['given_up' => $givenUp];
}

/** One sentence for tab and cron alike. */
function cloud_copies_summary(array $r): array
{
    $parts = [];
    if ($r['copied'])   $parts[] = t('%d Kopien angelegt', $r['copied']);
    if ($r['removed'])  $parts[] = t('%d Kopien entfernt', $r['removed']);
    if ($r['failed'])   $parts[] = t('%d fehlgeschlagen (kommen beim nächsten Lauf wieder dran)', $r['failed']);
    if ($r['given_up']) $parts[] = t('%d aufgegeben', $r['given_up']);
    return $parts;
}

/**
 * One chunk from the tab. Must run BEFORE any output.
 *
 * @return array|null message as [kind, text], or null if nothing ran
 */
function cloud_copies_process(PDO $pdo): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'cloud_copies') {
        return null;
    }
    csrf_check();
    if (!cloud_copies_ready($pdo)) {
        return ['error', t('Der Kopierlauf braucht die Migration 0098 — sie ist noch nicht eingespielt.')];
    }
    if (($_POST['action'] ?? '') === 'retry') {
        // Given-up rows get a fresh start — the tab's one manual lever,
        // for after the full cloud was emptied.
        $pdo->exec('UPDATE map_cloud_copies SET status = "waiting", attempts = 0, tried_at = NULL WHERE status = "given_up"');
        return ['hint', t('Aufgegebene Kopien werden wieder versucht.')];
    }
    $parts = cloud_copies_summary(cloud_copies_run($pdo, microtime(true) + CLOUD_COPIES_SECONDS));
    $open  = cloud_copies_pending($pdo);
    return [
        $open === 0 ? 'success' : 'hint',
        ($parts === [] ? t('Nichts zu tun.') : implode(' · ', $parts))
            . ($open === 0 ? ' — ' . t('fertig.') : ' · ' . t('noch %d offen', $open)),
    ];
}

/** The view. $message comes from cloud_copies_process(). */
function cloud_copies_render(PDO $pdo, ?array $message): void
{
    $target = url('pages/administration.php') . '#cloud_copies';
    if ($message !== null) {
        notice($message[0], $message[1], true);
    }
    if (!cloud_copies_ready($pdo)) {
        notice('hint', t('Der Kopierlauf braucht die Migration 0098 — sie ist noch nicht eingespielt.'));
        return;
    }
    $counts = $pdo->query(
        'SELECT SUM(status = "waiting")  AS waiting,
                SUM(status = "copied")   AS copied,
                SUM(status = "given_up") AS given_up,
                SUM(status = "deleting") AS deleting,
                COALESCE(SUM(IF(status = "copied", size, 0)), 0) AS bytes
           FROM map_cloud_copies'
    )->fetch();
    // The name is put together here: map_users carries first and last
    // name, not a display name — and tests/columns.php rightly refuses a
    // column that is not there.
    $stuck = $pdo->query(
        'SELECT c.error, c.tried_at, c.attempts, u.first_name, u.last_name, u.email
           FROM map_cloud_copies c JOIN map_users u ON u.id = c.user_id
          WHERE c.status = "given_up" ORDER BY c.tried_at DESC LIMIT 10'
    )->fetchAll();
    ?>
<p class="hint-text">
    <?= h(t('Jeder Mitbesitzer mit Cloud bekommt den vollständigen Bildsatz eines Fundes als eigene Kopie. Dieser Lauf trägt die Dateien von Cloud zu Cloud — in Häppchen, mit Wiederholung; unbeaufsichtigt macht dasselbe cronjobs/hourly.php.')) ?>
</p>
<div class="column-header">
    <h3><?= h(t('Kopien')) ?></h3>
    <form method="post" action="<?= h($target) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="cloud_copies">
        <button class="button small" type="submit" <?= (int) $counts['waiting'] + (int) $counts['deleting'] === 0 ? 'disabled' : '' ?>>
            <?= h(t('Ein Häppchen kopieren')) ?>
        </button>
    </form>
</div>
<div class="table-frame">
    <table class="list no-stack">
        <tbody>
        <tr><th><?= h(t('Wartend')) ?></th><td><?= (int) $counts['waiting'] ?></td></tr>
        <tr><th><?= h(t('Kopiert')) ?></th><td><?= (int) $counts['copied'] ?> · <?= h(cloud_size((int) $counts['bytes'])) ?></td></tr>
        <tr><th><?= h(t('Zum Entfernen')) ?></th><td><?= (int) $counts['deleting'] ?></td></tr>
        <tr><th><?= h(t('Aufgegeben')) ?></th><td><?= (int) $counts['given_up'] ?></td></tr>
        </tbody>
    </table>
</div>
<?php if ($stuck !== []): ?>
    <div class="column-header">
        <h3><?= h(t('Aufgegeben')) ?></h3>
        <form method="post" action="<?= h($target) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="cloud_copies">
            <input type="hidden" name="action" value="retry">
            <button class="button small subtle" type="submit"><?= h(t('Noch einmal versuchen')) ?></button>
        </form>
    </div>
    <ul class="person-list">
        <?php foreach ($stuck as $row): ?>
            <li class="person-row">
                <span class="person-name">
                    <?= h(trim((string) $row['first_name'] . ' ' . (string) $row['last_name']) ?: (string) $row['email']) ?>
                    <span class="item-data"><?= h((string) $row['error']) ?> · <?= h(t('%d Versuche', (int) $row['attempts'])) ?></span>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php
}
