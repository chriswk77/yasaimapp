<?php
/**
 * Moving to another cloud — the SWITCH (26.09.2026).
 *
 * „Umzugswerkzeug": every picture YASAIMAPP laid into one cloud goes over
 * to another — the voucher photos, the recordings, the calibration images
 * and the copies of the others' pictures on shared finds. AND THE FINDS'
 * OTHER FILES since 05.10.2026 (point_files.php, migration 0154): „Hier
 * kommt alles mit, alles, was über YASAIMAPP hochgeladen wurde." They go
 * where they would have gone in the new cloud from the start — the find's
 * folder, „Dateien", the folder they were dropped in.
 *
 * ONE CLOUD, ALWAYS. His rule of 27.08.2026 — „man kann immer nur zu
 * einem connected sein", and the reason the Cloud tab hides the other
 * tiles. The first draft broke it: for the length of the move it showed
 * both clouds connected side by side, and he caught it („da ist der Fehler
 * in deinem Plan"). So a move is a SWITCH. The move window is the one door
 * to another cloud while one is connected; the moment the user agrees at
 * the new provider (pages/cloud_return.php), that one IS the cloud — the
 * tile, new photos, the backup. The old one is no longer connected.
 * YASAIMAPP only keeps its KEY in the background, because nobody else
 * could hand out the old pictures, and deletes it once the last of them
 * has arrived. The key is a loan for the move, not a second connection:
 * cloud_connections() leaves the old row out, while cloud_storage_for() —
 * the door every picture is read through where it lies — still opens it.
 * So every picture stays visible the whole time, wherever it lies.
 *
 * NOTHING IS DELETED IN THE OLD CLOUD (his choice, the same evening): it
 * stays a complete copy until the user tidies it up himself. A move that
 * went wrong has lost nothing, and the Cloud tab says afterwards where the
 * old folder is, so it is not forgotten.
 *
 * STUCK IS NEVER THE END (his question: „den Schlüssel behalten … und
 * vielleicht am nächsten Tag den Umzug beenden"). The move stays open
 * without a deadline, and the key with it:
 *   - the new cloud FULL → the whole move waits (full_since); every run
 *     asks the new cloud once how much room there is and goes on by itself
 *     as soon as the next picture fits;
 *   - a cloud that does not answer → the move waits (problem), and the
 *     next run simply tries again;
 *   - a single picture that will not go → 'stuck' after
 *     CLOUD_MOVES_MAX_ATTEMPTS, with the reason, and tried again a day
 *     later; one that is gone from the old cloud or too big is stuck at
 *     once.
 * The end comes when everything has arrived, or when the user closes the
 * move himself („Umzug so abschließen") — only then the key goes.
 *
 * WHO CARRIES: the open Cloud tab, step by step (api/v1/cloud_move.php,
 * assets/js/cloudmove.js), and the five-minute cron bundle when the tab is
 * closed (cronjobs/every_5_minutes.php — his choice A, no cron entry of its own).
 * Both go through cloud_moves_run(), under one lock, so the two never carry
 * one picture twice.
 *
 * WHOLE FILES, NOT STREAMS: this hosting has no cURL (gdrive.php says so),
 * and PHP's own HTTP streams send a body only as a string. A picture is
 * therefore carried in memory, under the co-owner copies' ceiling; a bigger
 * one is stuck with that reason instead of costing the run its memory.
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';

/** One step from the open Cloud tab — well inside a web request's time. */
const CLOUD_MOVES_SECONDS = 20;

/** Tries before a picture counts as stuck. */
const CLOUD_MOVES_MAX_ATTEMPTS = 3;

/** A stuck picture is tried again after this long, by itself. */
const CLOUD_MOVES_RETRY_HOURS = 24;

/** The co-owner copies' ceiling (CLOUD_COPIES_MAX_BYTES), for the same memory. */
const CLOUD_MOVES_MAX_BYTES = 64 * 1024 * 1024;

/** The whole move waits: the new cloud is full. The picture is not to blame. */
class CloudMoveFull extends RuntimeException
{
}

/** The whole move waits: a cloud does not answer, or its key does not hold. */
class CloudMoveBlocked extends RuntimeException
{
}

/** This one picture cannot go at all — gone from the old cloud, too big. */
class CloudMoveStuck extends RuntimeException
{
}

function cloud_moves_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_cloud_moves') && table_exists($pdo, 'map_cloud_move_items');
}

/** The user's running move, or null. */
/** Can a move write down a find's other files (0154)? Until then they stay behind. */
function cloud_move_files_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        require_once __DIR__ . '/point_files.php';
        $kind = $pdo->query("SHOW COLUMNS FROM map_cloud_move_items LIKE 'kind'")->fetch();
        $ready = point_files_ready($pdo) && $kind !== false && str_contains((string) $kind['Type'], "'file'");
    }
    return $ready;
}

function cloud_move_open(PDO $pdo, int $userId): ?array
{
    if (!cloud_moves_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM map_cloud_moves WHERE user_id = ? AND status = "running" ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/** One move by its id, as it stands now. */
function cloud_move_get(PDO $pdo, int $moveId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM map_cloud_moves WHERE id = ?');
    $stmt->execute([$moveId]);
    return $stmt->fetch() ?: null;
}

/**
 * The last move that ended INTO this cloud, with its numbers — the footnote
 * on the tile says where the old folder is, so it is not forgotten.
 */
function cloud_move_last(PDO $pdo, int $userId, string $provider): ?array
{
    if (!cloud_moves_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM map_cloud_moves
          WHERE user_id = ? AND to_provider = ? AND status IN ("done", "closed")
          ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$userId, $provider]);
    $move = $stmt->fetch();
    return $move === false ? null : $move + cloud_move_progress($pdo, $move);
}

/** A provider's name as the user reads it. */
function cloud_move_name(string $provider): string
{
    return (string) (CLOUD_PROVIDERS[$provider]['name'] ?? $provider);
}

/**
 * Where a user can move to: a cloud proven for BOTH ways up
 * (CLOUD_PICTURE_PROVIDERS and CLOUD_UPLOAD_PROVIDERS — the new cloud must
 * take everything the old one took), set up on this server, reached through
 * a consent page, and not the one the pictures come from.
 *
 * NOT S3 AS A TARGET, for now: its connection is a form with endpoint and
 * keys, not a consent page, and the move window carries no such form. It
 * comes with S3's release, once somebody has tried S3 with a real bucket.
 *
 * @return string[] provider keys
 */
function cloud_move_targets(string $from): array
{
    $out = [];
    foreach (CLOUD_PROVIDERS as $key => $a) {
        if ($key !== $from && ($a['kind'] ?? '') === 'oauth'
            && in_array($key, CLOUD_PICTURE_PROVIDERS, true)
            && in_array($key, CLOUD_UPLOAD_PROVIDERS, true)
            && cloud_provider_usable($key)) {
            $out[] = $key;
        }
    }
    return $out;
}

/**
 * WHAT OF THIS USER LIES IN THIS CLOUD — counted from our rows, without a
 * call to the provider. The move window says what goes along, „Trennen"
 * says what would become invisible.
 *
 * Rows from before the provider was written down are pCloud's (COALESCE),
 * the one cloud there was then.
 *
 * A VOUCHER PHOTO WITH AN EMPTY cloud_fileid LIES IN NO CLOUD — `<> ""`,
 * not `IS NOT NULL`, here, in the top-up and in cloud_move_source alike
 * (12.11.2026). Such a row has nothing to carry: the move would ask the
 * old cloud for a file without an id, fail on it until the picture got
 * stuck, and the move would never end by itself. Found as a stray row in
 * the local demodata, where it broke tests/cloud_moves.php; `<> ""` is
 * false for NULL as well, so it covers both.
 *
 * @return array{photo: int, cloud: int, scale: int, copy: int, file: int, bytes: int}
 */
function cloud_pictures_held(PDO $pdo, int $userId, string $provider): array
{
    $out = ['photo' => 0, 'cloud' => 0, 'scale' => 0, 'copy' => 0, 'file' => 0, 'bytes' => 0];
    $count = static function (string $sql) use ($pdo, $userId, $provider): array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $provider]);
        $r = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
        return [(int) $r[0], (int) $r[1]];
    };

    [$out['photo'], $bytes] = $count(
        'SELECT COUNT(*), COALESCE(SUM(bytes), 0) FROM map_photos
          WHERE user_id = ? AND cloud_fileid <> "" AND COALESCE(cloud_provider, "pcloud") = ?');
    $out['bytes'] += $bytes;
    if (table_exists($pdo, 'map_cloud_files')) {
        [$out['cloud'], $bytes] = $count(
            'SELECT COUNT(*), COALESCE(SUM(size), 0) FROM map_cloud_files
              WHERE user_id = ? AND COALESCE(provider, "pcloud") = ?');
        $out['bytes'] += $bytes;
    }
    if (column_exists($pdo, 'map_photo_scales', 'provider')) {
        // A calibration image's size is nowhere written down — counted, not weighed.
        [$out['scale']] = $count(
            'SELECT COUNT(*), 0 FROM map_photo_scales
              WHERE user_id = ? AND fileid IS NOT NULL AND COALESCE(provider, "pcloud") = ?');
    }
    if (table_exists($pdo, 'map_cloud_copies')) {
        [$out['copy'], $bytes] = $count(
            'SELECT COUNT(*), COALESCE(SUM(size), 0) FROM map_cloud_copies
              WHERE user_id = ? AND status = "copied" AND fileid IS NOT NULL AND provider = ?');
        $out['bytes'] += $bytes;
    }
    if (table_exists($pdo, 'map_point_files')) {
        [$out['file'], $bytes] = $count(
            'SELECT COUNT(*), COALESCE(SUM(size), 0) FROM map_point_files WHERE user_id = ? AND provider = ?');
        $out['bytes'] += $bytes;
    }
    return $out;
}

/**
 * THE SWITCH — called by pages/cloud_return.php the moment the new cloud
 * is connected: from here on it is THE cloud, and the old one only lends
 * its key (see the head of this file).
 *
 * THE BACKUP STARTS OVER IN THE NEW CLOUD. Our bookkeeping of it names no
 * provider (cloud_disconnect() says why) and describes the old cloud's
 * folder; kept, it would tell the new cloud that everything is already
 * secured while its folder is empty. The old backups stay in the old
 * cloud, like everything else there.
 *
 * @return int the move's id
 * @throws RuntimeException
 */
function cloud_move_begin(PDO $pdo, int $userId, string $from, string $to): int
{
    if (!cloud_moves_ready($pdo)) {
        throw new RuntimeException(t('Der Umzug braucht die Migration 0142 — sie ist noch nicht eingespielt.'));
    }
    $all = cloud_connections($pdo, $userId, true);
    if ($from === $to || !isset($all[$from], $all[$to])) {
        throw new RuntimeException(t('Für den Umzug müssen die alte und die neue Cloud verbunden sein.'));
    }
    if (cloud_move_open($pdo, $userId) !== null) {
        throw new RuntimeException(t('Es läuft schon ein Umzug.'));
    }

    $pdo->prepare(
        'INSERT INTO map_cloud_moves (user_id, from_provider, to_provider, status, started_at)
         VALUES (?, ?, ?, "running", NOW())'
    )->execute([$userId, $from, $to]);
    $moveId = (int) $pdo->lastInsertId();

    if (table_exists($pdo, 'map_cloud_backups')) {
        $pdo->prepare('DELETE FROM map_cloud_backups WHERE user_id = ?')->execute([$userId]);
    }

    cloud_move_top_up($pdo, (array) cloud_move_get($pdo, $moveId));
    return $moveId;
}

/**
 * EVERY PICTURE STILL LYING IN THE OLD CLOUD becomes a row to carry — at
 * the switch, and again before every run: a voucher photo that a sweep laid
 * into the old cloud in the very second of the switch would otherwise stay
 * behind unseen. INSERT IGNORE on (move, kind, row): run it as often as one
 * likes.
 *
 * @return int rows added
 */
function cloud_move_top_up(PDO $pdo, array $move): int
{
    $args  = [(int) $move['id'], (int) $move['user_id'], (string) $move['from_provider']];
    $added = 0;
    $run = static function (string $sql) use ($pdo, $args, &$added): void {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        $added += $stmt->rowCount();
    };

    $run('INSERT IGNORE INTO map_cloud_move_items (move_id, kind, row_id, size)
          SELECT ?, "photo", p.id, COALESCE(p.bytes, 0) FROM map_photos p
           WHERE p.user_id = ? AND p.cloud_fileid <> "" AND COALESCE(p.cloud_provider, "pcloud") = ?');
    if (table_exists($pdo, 'map_cloud_files')) {
        $run('INSERT IGNORE INTO map_cloud_move_items (move_id, kind, row_id, size)
              SELECT ?, "cloud", f.id, COALESCE(f.size, 0) FROM map_cloud_files f
               WHERE f.user_id = ? AND COALESCE(f.provider, "pcloud") = ?');
    }
    if (column_exists($pdo, 'map_photo_scales', 'provider')) {
        $run('INSERT IGNORE INTO map_cloud_move_items (move_id, kind, row_id, size)
              SELECT ?, "scale", s.id, 0 FROM map_photo_scales s
               WHERE s.user_id = ? AND s.fileid IS NOT NULL AND COALESCE(s.provider, "pcloud") = ?');
    }
    if (table_exists($pdo, 'map_cloud_copies')) {
        $run('INSERT IGNORE INTO map_cloud_move_items (move_id, kind, row_id, size)
              SELECT ?, "copy", c.id, COALESCE(c.size, 0) FROM map_cloud_copies c
               WHERE c.user_id = ? AND c.status = "copied" AND c.fileid IS NOT NULL AND c.provider = ?');
    }
    if (cloud_move_files_ready($pdo)) {
        $run('INSERT IGNORE INTO map_cloud_move_items (move_id, kind, row_id, size)
              SELECT ?, "file", f.id, COALESCE(f.size, 0) FROM map_point_files f
               WHERE f.user_id = ? AND f.provider = ?');
    }
    return $added;
}

/**
 * The move's numbers, for the bar and for the footnote.
 *
 * @return array{total: int, moved: int, waiting: int, stuck: int, bytes: int, bytes_moved: int}
 */
function cloud_move_progress(PDO $pdo, array $move): array
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total,
                COALESCE(SUM(status = "moved"), 0) AS moved,
                COALESCE(SUM(status = "waiting"), 0) AS waiting,
                COALESCE(SUM(status = "stuck"), 0) AS stuck,
                COALESCE(SUM(size), 0) AS bytes,
                COALESCE(SUM(IF(status = "moved", size, 0)), 0) AS bytes_moved,
                COALESCE(SUM(kind = "file"), 0) AS files
           FROM map_cloud_move_items WHERE move_id = ?'
    );
    $stmt->execute([(int) $move['id']]);
    $r = $stmt->fetch() ?: [];
    $out = [];
    // files: how many of them are a find's other files — then the lines
    // say „Dateien", not „Bilder" (cloud_move_count_text).
    foreach (['total', 'moved', 'waiting', 'stuck', 'bytes', 'bytes_moved', 'files'] as $key) {
        $out[$key] = (int) ($r[$key] ?? 0);
    }
    return $out;
}

/**
 * What the panel and the tab's steps show: where the move stands, the
 * numbers, and why it waits, if it does.
 */
function cloud_move_state(PDO $pdo, array $move): array
{
    return [
        'status'  => (string) $move['status'],
        'full'    => $move['full_since'] !== null,
        'problem' => $move['problem'] === null ? null : (string) $move['problem'],
        'from'    => cloud_move_name((string) $move['from_provider']),
        'to'      => cloud_move_name((string) $move['to_provider']),
    ] + cloud_move_progress($pdo, $move);
}

/**
 * The stuck pictures, each with what it is and why — named the way the
 * user knows them: the file, what kind of picture, and the find.
 *
 * @return list<array{name: string, what: string, error: string}>
 */
function cloud_move_stuck(PDO $pdo, array $move, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        'SELECT kind, row_id, error FROM map_cloud_move_items
          WHERE move_id = ? AND status = "stuck" ORDER BY id LIMIT ' . max(1, $limit)
    );
    $stmt->execute([(int) $move['id']]);
    $out = [];
    foreach ($stmt->fetchAll() as $item) {
        [$name, $what] = cloud_move_describe($pdo, (string) $item['kind'], (int) $item['row_id']);
        $out[] = ['name' => $name, 'what' => $what, 'error' => (string) $item['error']];
    }
    return $out;
}

/** @return array{0: string, 1: string} the name, and what and where it is */
function cloud_move_describe(PDO $pdo, string $kind, int $rowId): array
{
    $sql = match ($kind) {
        'photo' => 'SELECT COALESCE(ph.original_name, ph.filename) AS name, pt.title
                      FROM map_photos ph LEFT JOIN map_points pt ON pt.id = ph.point_id WHERE ph.id = ?',
        'cloud' => 'SELECT f.name, pt.title
                      FROM map_cloud_files f LEFT JOIN map_points pt ON pt.id = f.point_id WHERE f.id = ?',
        'scale' => 'SELECT s.name, NULL AS title FROM map_photo_scales s WHERE s.id = ?',
        'file'  => 'SELECT f.name, pt.title
                      FROM map_point_files f LEFT JOIN map_points pt ON pt.id = f.point_id WHERE f.id = ?',
        default => 'SELECT COALESCE(f.name, ph.original_name, ph.filename) AS name, pt.title
                      FROM map_cloud_copies c
                      LEFT JOIN map_cloud_files f ON c.origin_kind = "cloud" AND f.id = c.origin_id
                      LEFT JOIN map_photos ph ON c.origin_kind = "photo" AND ph.id = c.origin_id
                      LEFT JOIN map_points pt ON pt.id = COALESCE(f.point_id, ph.point_id)
                     WHERE c.id = ?',
    };
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$rowId]);
    $r     = $stmt->fetch() ?: [];
    $name  = (string) ($r['name'] ?? '');
    $title = trim((string) ($r['title'] ?? ''));

    if ($kind === 'scale') {
        return [t('Eichbild'), t('Vorlage „%s"', $name)];
    }
    $what = match ($kind) {
        'photo' => $title !== '' ? t('Belegfoto bei „%s"', $title) : t('Belegfoto bei einem Fund ohne Namen'),
        'cloud' => $title !== '' ? t('Aufnahme bei „%s"', $title) : t('Aufnahme bei einem Fund ohne Namen'),
        'file'  => $title !== '' ? t('Datei bei „%s"', $title) : t('Datei bei einem Fund ohne Namen'),
        default => $title !== '' ? t('Kopie eines Bildes bei „%s"', $title) : t('Kopie eines Bildes bei einem Fund ohne Namen'),
    };
    return [$name !== '' ? $name : t('ohne Namen'), $what];
}

/**
 * THE CARRYING — one loop for the open tab and for the cron job (the rule
 * of cronjobs/: the unattended twin calls the same run the tab does).
 *
 * $carry, $free and $undo are injectable for the test, like the copy
 * worker's transfer: the bookkeeping — what counts as moved, how a failure
 * counts, when a move waits and when it ends — must be provable without a
 * cloud on the machine. The real ones are the functions below.
 *
 * @return array{moved: int, failed: int, stuck: int, waiting: int, done: int}
 */
function cloud_moves_run(PDO $pdo, float $deadline, ?int $onlyUser = null, ?callable $carry = null,
                         ?callable $free = null, ?callable $undo = null): array
{
    $out = ['moved' => 0, 'failed' => 0, 'stuck' => 0, 'waiting' => 0, 'done' => 0];
    if (!cloud_moves_ready($pdo)) {
        return $out;
    }
    $carry ??= 'cloud_move_carry';
    $free  ??= 'cloud_move_free';
    $undo  ??= 'cloud_move_undo';

    $stmt = $pdo->prepare('SELECT * FROM map_cloud_moves WHERE status = "running"'
        . ($onlyUser === null ? '' : ' AND user_id = ?') . ' ORDER BY id');
    $stmt->execute($onlyUser === null ? [] : [$onlyUser]);

    foreach ($stmt->fetchAll() as $move) {
        if (microtime(true) >= $deadline) {
            break;
        }
        cloud_move_top_up($pdo, $move);

        /* FULL: ask for room once, instead of knocking picture by picture.
           The next picture's size is the measure; one whose size nobody
           wrote down (a calibration image) goes as soon as there is any
           room at all. A cloud that names no limit is simply tried. */
        if ($move['full_since'] !== null) {
            $next = cloud_move_next($pdo, $move);
            try {
                $room = $free($pdo, $move);
            } catch (RuntimeException $e) {
                $room = 0;   // cannot tell — it waits, the next run asks again
            }
            if ($room !== null && $room < max(1, (int) ($next['size'] ?? 0))) {
                $out['waiting']++;
                continue;
            }
            $pdo->prepare('UPDATE map_cloud_moves SET full_since = NULL WHERE id = ?')->execute([(int) $move['id']]);
            $move['full_since'] = null;
        }

        while (microtime(true) < $deadline) {
            $item = cloud_move_next($pdo, $move);
            if ($item === null) {
                break;
            }
            $result = cloud_move_one($pdo, $move, $item, $carry, $undo);
            if ($result === 'full' || $result === 'blocked') {
                $out['waiting']++;
                continue 2;
            }
            if (isset($out[$result])) {
                $out[$result]++;
            }
        }

        // THE END: nothing left in the old cloud — then the key goes. The
        // top-up once more first, for a picture laid there in the meantime.
        $left = $pdo->prepare('SELECT COUNT(*) FROM map_cloud_move_items WHERE move_id = ? AND status <> "moved"');
        $left->execute([(int) $move['id']]);
        if ((int) $left->fetchColumn() === 0 && cloud_move_top_up($pdo, $move) === 0) {
            cloud_move_finish($pdo, $move, 'done');
            $out['done']++;
        }
    }
    return $out;
}

/**
 * The next picture that is due: the waiting ones first — after a short
 * back-off once they failed, ten minutes per attempt —, then the stuck
 * ones whose day has passed.
 */
function cloud_move_next(PDO $pdo, array $move): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM map_cloud_move_items
          WHERE move_id = ?
            AND ((status = "waiting" AND (tried_at IS NULL OR tried_at < NOW() - INTERVAL (attempts * 10) MINUTE))
              OR (status = "stuck" AND tried_at < NOW() - INTERVAL ? HOUR))
          ORDER BY status = "stuck", id
          LIMIT 1'
    );
    $stmt->execute([(int) $move['id'], CLOUD_MOVES_RETRY_HOURS]);
    return $stmt->fetch() ?: null;
}

/**
 * One picture: carried, written down, or counted as a failure.
 *
 * @return string moved | failed | stuck | dropped | full | blocked
 */
function cloud_move_one(PDO $pdo, array $move, array $item, callable $carry, callable $undo): string
{
    $itemId = (int) $item['id'];
    $moveId = (int) $move['id'];
    $note = static fn(string $message): string => mb_substr($message, 0, 255);

    /* ITS TRIES USED UP WITHOUT AN ANSWER: the process died while carrying
       it — the host's 120 seconds, or memory, which no catch can see. Then
       it is stuck now, with that said, instead of being tried again and
       again after an ever longer pause. */
    if ($item['status'] === 'waiting' && (int) $item['attempts'] >= CLOUD_MOVES_MAX_ATTEMPTS) {
        $pdo->prepare('UPDATE map_cloud_move_items SET status = "stuck", error = ? WHERE id = ?')
            ->execute([$note((string) ($item['error'] ?? '') !== '' ? (string) $item['error']
                : t('Beim Tragen dieses Bildes brach der Server ab — vielleicht ist es zu groß.')), $itemId]);
        return 'stuck';
    }

    /* THE ATTEMPT IS COUNTED BEFORE IT IS MADE, as at the copies: a run the
       host kills mid-transfer is then a failed attempt like any other, not
       a row that stalls the move for good. A stuck picture's daily try
       starts at the limit — one more failure puts it back where it was. */
    $pdo->prepare(
        'UPDATE map_cloud_move_items
            SET attempts = IF(status = "stuck", ?, attempts + 1), status = "waiting", tried_at = NOW()
          WHERE id = ?'
    )->execute([CLOUD_MOVES_MAX_ATTEMPTS, $itemId]);

    $row = cloud_move_source($pdo, $move, $item);
    if ($row === null) {
        // Deleted meanwhile, or no longer in the old cloud: nothing to carry.
        $pdo->prepare('DELETE FROM map_cloud_move_items WHERE id = ?')->execute([$itemId]);
        return 'dropped';
    }

    try {
        $at = $carry($pdo, $move, $item, $row);
    } catch (CloudMoveFull $e) {
        // Not the picture's fault: its attempt is given back, the move waits.
        $pdo->prepare('UPDATE map_cloud_move_items SET attempts = GREATEST(attempts - 1, 0), tried_at = NULL WHERE id = ?')
            ->execute([$itemId]);
        $pdo->prepare('UPDATE map_cloud_moves SET full_since = COALESCE(full_since, NOW()) WHERE id = ?')
            ->execute([$moveId]);
        return 'full';
    } catch (CloudMoveBlocked $e) {
        $pdo->prepare('UPDATE map_cloud_move_items SET attempts = GREATEST(attempts - 1, 0), tried_at = NULL WHERE id = ?')
            ->execute([$itemId]);
        $pdo->prepare('UPDATE map_cloud_moves SET problem = ? WHERE id = ?')->execute([$note($e->getMessage()), $moveId]);
        return 'blocked';
    } catch (CloudMoveStuck $e) {
        $pdo->prepare('UPDATE map_cloud_move_items SET status = "stuck", error = ? WHERE id = ?')
            ->execute([$note($e->getMessage()), $itemId]);
        return 'stuck';
    } catch (RuntimeException $e) {
        error_log('cloud_move_one (' . $item['kind'] . ' ' . (int) $item['row_id'] . '): ' . $e->getMessage());
        $pdo->prepare(
            'UPDATE map_cloud_move_items SET error = ?, status = IF(attempts >= ?, "stuck", "waiting") WHERE id = ?'
        )->execute([$note($e->getMessage()), CLOUD_MOVES_MAX_ATTEMPTS, $itemId]);
        return (int) $pdo->query('SELECT status = "stuck" FROM map_cloud_move_items WHERE id = ' . $itemId)->fetchColumn() === 1
            ? 'stuck' : 'failed';
    }

    /* WRITTEN DOWN ONLY IF THE ROW IS STILL THE ONE THAT WAS CARRIED — the
       same cloud, the same file. Deleted meanwhile, or moved in the old
       cloud under a new id (S3), the new copy belongs to nothing: it goes
       again, and the next top-up brings the row back if it is still there. */
    if (cloud_move_repoint($pdo, $move, $item, $row, $at) === 0) {
        try {
            $undo($pdo, $move, $at);
        } catch (RuntimeException $e) {
            error_log('cloud_move_undo (' . $item['kind'] . ' ' . (int) $item['row_id'] . '): ' . $e->getMessage());
        }
        $pdo->prepare('DELETE FROM map_cloud_move_items WHERE id = ?')->execute([$itemId]);
        return 'dropped';
    }

    $pdo->prepare('UPDATE map_cloud_move_items SET status = "moved", moved_at = NOW(), error = NULL, size = ? WHERE id = ?')
        ->execute([(int) ($at['size'] ?? $item['size']), $itemId]);
    $pdo->prepare('UPDATE map_cloud_moves SET problem = NULL WHERE id = ? AND problem IS NOT NULL')->execute([$moveId]);
    cloud_move_after($pdo, $move, $item, $at);
    /* THE PATCHES OF „ENTFERNEN" exist only in the old cloud (11.11.2026,
       develop_patches.php) — the sidecar is written anew from our rows,
       but the AI's pixels cannot be, so they are carried like the picture.
       Best effort: one that does not come brings its place back, nothing
       worse, and the move goes on. */
    if ($item['kind'] === 'photo' || $item['kind'] === 'cloud') {
        require_once __DIR__ . '/develop_patches.php';
        // Of every edit (0192), not only the valid one — and of everybody else's on the picture (0194).
        $patchIds = develop_picture_patch_ids($pdo, $item['kind'], $row);
        if ($patchIds !== []) {
            try {
                [$fromCloud, $toCloud] = cloud_move_storages($pdo, $move);
                develop_patches_carry($fromCloud, cloud_move_old_id($item, $row), $toCloud, (string) $at['fileid'], $patchIds);
            } catch (Throwable $e) {
                error_log('cloud_move patches (' . $item['kind'] . ' ' . (int) $item['row_id'] . '): ' . $e->getMessage());
            }
        }
    }
    return 'moved';
}

/** The picture's id in the cloud it comes from. */
function cloud_move_old_id(array $item, array $row): string
{
    return (string) ($item['kind'] === 'photo' ? ($row['cloud_fileid'] ?? '') : ($row['fileid'] ?? ''));
}

/** The row an item stands for — only while it still lies in the old cloud. */
function cloud_move_source(PDO $pdo, array $move, array $item): ?array
{
    $sql = match ((string) $item['kind']) {
        'photo' => 'SELECT * FROM map_photos
                     WHERE id = ? AND user_id = ? AND cloud_fileid <> "" AND COALESCE(cloud_provider, "pcloud") = ?',
        'cloud' => 'SELECT * FROM map_cloud_files
                     WHERE id = ? AND user_id = ? AND COALESCE(provider, "pcloud") = ?',
        'scale' => 'SELECT * FROM map_photo_scales
                     WHERE id = ? AND user_id = ? AND fileid IS NOT NULL AND COALESCE(provider, "pcloud") = ?',
        'file'  => 'SELECT * FROM map_point_files WHERE id = ? AND user_id = ? AND provider = ?',
        'copy'  => 'SELECT c.*, COALESCE(ph.point_id, f.point_id) AS point_id
                      FROM map_cloud_copies c
                      LEFT JOIN map_photos ph ON c.origin_kind = "photo" AND ph.id = c.origin_id
                      LEFT JOIN map_cloud_files f ON c.origin_kind = "cloud" AND f.id = c.origin_id
                     WHERE c.id = ? AND c.user_id = ? AND c.status = "copied" AND c.fileid IS NOT NULL AND c.provider = ?',
        default => null,
    };
    if ($sql === null) {
        return null;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int) $item['row_id'], (int) $move['user_id'], (string) $move['from_provider']]);
    $row = $stmt->fetch();
    return $row === false || cloud_move_old_id($item, $row) === '' ? null : $row;
}

/**
 * The row learns where its picture lies now — guarded by where it lay, so a
 * row that changed during the carrying is not bent onto the wrong file.
 *
 * @return int rows changed: 1, or 0 when the row changed meanwhile
 */
function cloud_move_repoint(PDO $pdo, array $move, array $item, array $row, array $at): int
{
    $to     = (string) $move['to_provider'];
    $from   = (string) $move['from_provider'];
    $new    = (string) $at['fileid'];
    $folder = (string) ($at['folderid'] ?? '');
    $path   = mb_substr((string) ($at['path'] ?? ''), 0, 512);
    $old    = cloud_move_old_id($item, $row);
    $id     = (int) $item['row_id'];
    $user   = (int) $move['user_id'];

    switch ((string) $item['kind']) {
        case 'photo':
            $stmt = $pdo->prepare(
                'UPDATE map_photos SET cloud_provider = ?, cloud_fileid = ?, cloud_folderid = ?, cloud_path = ?
                  WHERE id = ? AND user_id = ? AND cloud_fileid = ? AND COALESCE(cloud_provider, "pcloud") = ?'
            );
            $stmt->execute([$to, $new, $folder === '' ? null : $folder, $path, $id, $user, $old, $from]);
            cloud_move_named($pdo, 'map_photos', 'cloud_name', $id, $at, $row, $stmt->rowCount());
            return $stmt->rowCount();
        case 'cloud':
            $stmt = $pdo->prepare(
                'UPDATE map_cloud_files SET provider = ?, fileid = ?, folderid = ?, path = ?
                  WHERE id = ? AND user_id = ? AND fileid = ? AND COALESCE(provider, "pcloud") = ?'
            );
            $stmt->execute([$to, $new, $folder === '' ? null : $folder, $path, $id, $user, $old, $from]);
            cloud_move_named($pdo, 'map_cloud_files', 'name', $id, $at, $row, $stmt->rowCount());
            return $stmt->rowCount();
        case 'scale':
            $stmt = $pdo->prepare(
                'UPDATE map_photo_scales SET provider = ?, fileid = ?
                  WHERE id = ? AND user_id = ? AND fileid = ? AND COALESCE(provider, "pcloud") = ?'
            );
            $stmt->execute([$to, $new, $id, $user, $old, $from]);
            return $stmt->rowCount();
        case 'copy':
            $stmt = $pdo->prepare(
                'UPDATE map_cloud_copies SET provider = ?, fileid = ?, folderid = ?, path = ?, size = ?
                  WHERE id = ? AND user_id = ? AND fileid = ? AND provider = ? AND status = "copied"'
            );
            $stmt->execute([$to, $new, $folder === '' ? null : $folder, $path, (int) ($at['size'] ?? 0),
                $id, $user, $old, $from]);
            cloud_move_named($pdo, 'map_cloud_copies', 'name', $id, $at, $row, $stmt->rowCount());
            return $stmt->rowCount();
        case 'file':
            // The file and the „Dateien" folder it now hangs under — the
            // folder moves with the find in the new cloud from here on.
            $stmt = $pdo->prepare(
                'UPDATE map_point_files SET provider = ?, fileid = ?, folderid = ?, home_folderid = ?, home_path = ?, name = ?
                  WHERE id = ? AND user_id = ? AND fileid = ? AND provider = ?'
            );
            $stmt->execute([$to, $new, $folder === '' ? null : $folder,
                (string) ($at['home_folderid'] ?? $folder), mb_substr((string) ($at['home_path'] ?? $path), 0, 512),
                mb_substr((string) ($at['name'] ?? $row['name']), 0, 255), $id, $user, $old, $from]);
            return $stmt->rowCount();
    }
    return 0;
}

/**
 * The name the picture carries in the new cloud, written down (0151) —
 * the names are kept by comparing it with the wanted one
 * (cloud_names.php), and a stale one would look right while the file
 * says otherwise. And the find due, should the new cloud have answered
 * with „… (1)".
 */
function cloud_move_named(PDO $pdo, string $table, string $column, int $id, array $at, array $row, int $changed): void
{
    if ($changed !== 1 || !isset($at['name']) || !cloud_names_ready($pdo)) {
        return;
    }
    $pdo->prepare('UPDATE ' . $table . ' SET ' . $column . ' = ? WHERE id = ?')
        ->execute([mb_substr((string) $at['name'], 0, 255), $id]);
    if ((int) ($row['point_id'] ?? 0) > 0) {
        require_once __DIR__ . '/cloud_names.php';
        cloud_names_touch($pdo, (int) $row['point_id']);
    }
}

/**
 * The measures travel as a sidecar with the picture, as everywhere — in
 * the folder's „Bearbeitung" (measure_sidecar_store) — written anew in the new cloud from OUR rows,
 * not carried over, so it says what the record says today. Voucher photos
 * and recordings only: a copy's measures live on its origin, and a
 * calibration image has none. Best effort, like every sidecar.
 */
function cloud_move_after(PDO $pdo, array $move, array $item, array $at): void
{
    $table = match ((string) $item['kind']) {
        'photo' => 'map_photos',
        'cloud' => 'map_cloud_files',
        default => null,
    };
    if ($table === null) {
        return;
    }
    require_once __DIR__ . '/photos.php';
    require_once __DIR__ . '/photo_cloud.php';

    $stmt = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = ?');
    $stmt->execute([(int) $item['row_id']]);
    $row = $stmt->fetch();
    if ($row === false) {
        return;
    }
    $scale    = photo_scale_read($row['scale'] ?? null);
    $measures = photo_measures_read($row['measures'] ?? null);
    $title    = photo_title_clean($row['title'] ?? null);
    $media    = photo_media_read($row['media'] ?? null);
    $develop  = photo_develop_read($row['develop'] ?? null);
    if ($scale === null && $measures === [] && $title === null && $media === null && $develop === null) {
        return;
    }
    measure_sidecar_store($pdo, (int) $move['user_id'], (string) $move['to_provider'], (string) $at['fileid'],
        $scale, $measures,
        isset($row['width']) && $row['width'] !== null ? (int) $row['width'] : null,
        isset($row['height']) && $row['height'] !== null ? (int) $row['height'] : null,
        $title, $row['original_name'] ?? null, $media, $develop);
}

/**
 * CARRY ONE PICTURE — the real transfer. Read where it lies, lay it where
 * the app would have laid it in the new cloud from the start (the genus
 * tree, „Unbestimmt", „Kalibrierung"), and at S3 give it the previews S3
 * cannot draw.
 *
 * @return array{fileid: string, folderid: string, path: string, size: int}
 * @throws CloudMoveFull|CloudMoveBlocked|CloudMoveStuck|RuntimeException
 */
function cloud_move_carry(PDO $pdo, array $move, array $item, array $row): array
{
    $userId = (int) $move['user_id'];
    [$from, $to, $home] = cloud_move_storages($pdo, $move);
    $old = cloud_move_old_id($item, $row);

    $meta = storage_file_meta($from, $old);
    if ($meta === null) {
        throw new CloudMoveStuck(t('In %s nicht mehr zu finden.', cloud_move_name((string) $move['from_provider'])));
    }
    if ((int) $meta['size'] > CLOUD_MOVES_MAX_BYTES) {
        throw new CloudMoveStuck(t('Zu groß für den Umzug (%s). Der Server trägt Dateien bis %s.',
            cloud_size((int) $meta['size']), cloud_size(CLOUD_MOVES_MAX_BYTES)));
    }
    $name = trim((string) $meta['name']) !== '' ? (string) $meta['name'] : cloud_move_fallback_name($item, $row);

    if ($item['kind'] === 'scale') {
        // Where the calibration images always go (api/v1/calibration.php).
        $made   = storage_folder_ensure($to, $home, 'Kalibrierung');
        $folder = ['folderid' => $made['folderid'], 'path' => ['Kalibrierung']];
    } else {
        // BY ID ALONE, like photo_to_cloud(): a picture may hang on
        // somebody else's find, and it is filed under the find it shows.
        $stmt = $pdo->prepare('SELECT * FROM map_points WHERE id = ? LIMIT 1');
        $stmt->execute([(int) ($row['point_id'] ?? 0)]);
        $point = $stmt->fetch();
        if ($point === false) {
            throw new CloudMoveStuck(t('Der Fund zu diesem Bild ist nicht mehr da.'));
        }
        $folder = cloud_target_folder($to, $home, $pdo, $userId, $point);
    }
    /* A FIND'S OTHER FILE goes one storey further: into the find's
       „Dateien" folder and the folder it was dropped in, as the tab would
       have laid it in the new cloud (point_files.php). */
    $files = null;
    if ($item['kind'] === 'file') {
        require_once __DIR__ . '/point_files.php';
        $made  = storage_folder_ensure($to, $folder['folderid'], POINT_FILES_FOLDER);
        $files = ['folderid' => (string) $made['folderid'],
                  'path' => implode('/', (array) $folder['path']) . '/' . POINT_FILES_FOLDER];
        $dir = (string) ($row['dir'] ?? '');
        $folder = $dir === ''
            ? ['folderid' => $files['folderid'], 'path' => explode('/', $files['path'])]
            : ['folderid' => storage_path_ensure($to, $files['folderid'], explode('/', $dir)),
               'path' => explode('/', $files['path'] . '/' . $dir)];
    }

    $bytes = storage_file_bytes($from, $old, CLOUD_MOVES_MAX_BYTES);
    // The moment a voucher photo was taken stays its file's time, as at
    // the first push (photo_to_cloud).
    $taken = $item['kind'] === 'photo' ? (strtotime((string) ($row['taken_at'] ?? '')) ?: null) : null;
    try {
        // A file keeps the type it came with; a picture is typed by its name.
        $type = $item['kind'] === 'file' && (string) ($row['mime'] ?? '') !== '' ? (string) $row['mime'] : cloud_picture_type($name);
        $up = storage_upload_bytes($to, $folder['folderid'], $bytes, $name, $taken, false, $type);
    } catch (RuntimeException $e) {
        if (cloud_full_error($e->getMessage())) {
            throw new CloudMoveFull($e->getMessage(), 0, $e);
        }
        throw $e;
    }
    $size = strlen($bytes);
    unset($bytes);
    $new = (string) $up['fileid'];

    if ($to['provider'] === 's3' && $item['kind'] !== 'scale' && $item['kind'] !== 'file') {
        cloud_move_s3_previews($from, $old, $to, (string) $home, $new);
    }

    return [
        'fileid'   => $new,
        'folderid' => (string) $folder['folderid'],
        'path'     => implode('/', (array) $folder['path']),
        'size'     => $size,
        // As the new cloud wrote it — „… (1)" if the name was taken there.
        'name'     => (string) ($up['name'] ?? $name),
    ] + ($files === null ? [] : ['home_folderid' => $files['folderid'], 'home_path' => $files['path']]);
}

/**
 * Both clouds of a move, opened — once per move and request. Either one
 * not answering stops the move for this run, not the picture.
 *
 * @return array{0: array, 1: array, 2: string|int} the old cloud, the new one, the app's folder in the new one
 * @throws CloudMoveBlocked
 */
function cloud_move_storages(PDO $pdo, array $move): array
{
    static $open = [];
    $id = (int) $move['id'];
    if (!isset($open[$id])) {
        $userId = (int) $move['user_id'];
        $from = cloud_storage_for($pdo, $userId, (string) $move['from_provider']);
        if ($from === null) {
            throw new CloudMoveBlocked(t('%s gibt deine Bilder gerade nicht heraus. Der Umzug versucht es von selbst wieder.',
                cloud_move_name((string) $move['from_provider'])));
        }
        $to = cloud_storage_for($pdo, $userId, (string) $move['to_provider']);
        if ($to === null) {
            throw new CloudMoveBlocked(t('%s ist gerade nicht erreichbar. Der Umzug versucht es von selbst wieder.',
                cloud_move_name((string) $move['to_provider'])));
        }
        try {
            $home = cloud_home_resolve($pdo, $userId, $to);
        } catch (RuntimeException $e) {
            throw new CloudMoveBlocked($e->getMessage(), 0, $e);
        }
        $open[$id] = [$from, $to, $home];
    }
    return $open[$id];
}

/** A name for a picture whose old cloud named none. */
function cloud_move_fallback_name(array $item, array $row): string
{
    if ($item['kind'] === 'photo') {
        require_once __DIR__ . '/photo_cloud.php';
        return photo_cloud_name($row);
    }
    $name = cloud_file_name_clean((string) ($row['name'] ?? ''));
    return $name !== '' ? $name : 'Bild-' . (int) $item['row_id'] . '.jpg';
}

/**
 * S3 DRAWS NO PREVIEWS — the old cloud's are fetched and laid into
 * „.vorschau" beside the new key (s3_preview_key), at the two edges the
 * photo window asks for. Best effort: without them the tile loads the
 * picture itself. A candidate larger than a preview can be is the old
 * cloud's fallback to the original, and is left out.
 */
function cloud_move_s3_previews(array $from, string $old, array $to, string $home, string $new): void
{
    foreach ([320, 2048] as $edge) {
        try {
            foreach (storage_file_fetch($from, $old, $edge) as $at) {
                $bytes = @file_get_contents($at['url'], false, stream_context_create(['http' => [
                    'timeout'         => 60,
                    'header'          => $at['headers'],
                    'follow_location' => ($at['follow'] ?? true) ? 1 : 0,
                    'ignore_errors'   => false,
                ]]), 0, 4 * 1024 * 1024 + 1);
                if ($bytes === false || $bytes === '' || strlen($bytes) > 4 * 1024 * 1024) {
                    continue;
                }
                $key   = s3_preview_key($home, $new, $edge);
                $slash = strrpos($key, '/');
                s3_upload_bytes($to['token'], substr($key, 0, $slash + 1), $bytes, substr($key, $slash + 1),
                    null, true, 'image/jpeg');
                break;
            }
        } catch (RuntimeException $e) {
            error_log('cloud_move_s3_previews (' . $new . ', ' . $edge . '): ' . $e->getMessage());
        }
    }
}

/** How much room the new cloud has — null when it names no limit. */
function cloud_move_free(PDO $pdo, array $move): ?int
{
    $to = cloud_storage_for($pdo, (int) $move['user_id'], (string) $move['to_provider']);
    return $to === null ? 0 : storage_free($to);
}

/** A copy that belongs to nothing goes again — at S3 with its previews. */
function cloud_move_undo(PDO $pdo, array $move, array $at): void
{
    $userId = (int) $move['user_id'];
    $to = cloud_storage_for($pdo, $userId, (string) $move['to_provider']);
    if ($to !== null) {
        storage_picture_delete($pdo, $userId, $to, (string) $at['fileid']);
    }
}

/**
 * THE END of a move — everything arrived ('done'), or closed by hand with
 * pictures left behind ('closed'). The old cloud's KEY goes: its account
 * row, and nothing else — cloud_disconnect() would also clear the backup's
 * bookkeeping, which since the switch belongs to the new cloud. Not one
 * file in the old cloud is touched (his choice): the copies that were
 * still to be removed there are simply forgotten, because without the key
 * nobody could remove them any more.
 */
function cloud_move_finish(PDO $pdo, array $move, string $status): void
{
    $stmt = $pdo->prepare(
        'UPDATE map_cloud_moves SET status = ?, finished_at = NOW(), full_since = NULL, problem = NULL
          WHERE id = ? AND status = "running"'
    );
    $stmt->execute([$status, (int) $move['id']]);
    if ($stmt->rowCount() === 0) {
        return;   // ended already — by the tab or by the cron, whichever came first
    }
    $pdo->prepare('DELETE FROM map_cloud_accounts WHERE user_id = ? AND provider = ?')
        ->execute([(int) $move['user_id'], (string) $move['from_provider']]);
    if (table_exists($pdo, 'map_cloud_copies')) {
        $pdo->prepare('DELETE FROM map_cloud_copies WHERE user_id = ? AND provider = ? AND status = "deleting"')
            ->execute([(int) $move['user_id'], (string) $move['from_provider']]);
    }
}

/**
 * „Nochmal versuchen": the stuck pictures and the ones in their back-off
 * start over, and a move that waited for room or an answer asks again at
 * once.
 *
 * @return int pictures that start over
 */
function cloud_move_retry(PDO $pdo, int $userId): int
{
    $move = cloud_move_open($pdo, $userId);
    if ($move === null) {
        return 0;
    }
    $stmt = $pdo->prepare(
        'UPDATE map_cloud_move_items SET status = "waiting", attempts = 0, tried_at = NULL, error = NULL
          WHERE move_id = ? AND (status = "stuck" OR (status = "waiting" AND attempts > 0))'
    );
    $stmt->execute([(int) $move['id']]);
    $pdo->prepare('UPDATE map_cloud_moves SET full_since = NULL, problem = NULL WHERE id = ?')->execute([(int) $move['id']]);
    return $stmt->rowCount();
}

/**
 * „Umzug so abschließen": what has not arrived stays in the old cloud, and
 * the key goes.
 *
 * @return int pictures left behind
 */
function cloud_move_close(PDO $pdo, int $userId): int
{
    $move = cloud_move_open($pdo, $userId);
    if ($move === null) {
        return 0;
    }
    $left = cloud_move_progress($pdo, $move);
    cloud_move_finish($pdo, $move, 'closed');
    return $left['waiting'] + $left['stuck'];
}

/** The cron line — the same voice as the copies'. */
function cloud_moves_summary(array $r): array
{
    $parts = [];
    if ($r['moved'])   $parts[] = t('%d Bilder umgezogen', $r['moved']);
    if ($r['failed'])  $parts[] = t('%d fehlgeschlagen (kommen beim nächsten Lauf wieder dran)', $r['failed']);
    if ($r['stuck'])   $parts[] = t('%d hängen', $r['stuck']);
    if ($r['waiting']) $parts[] = t('%d Umzüge warten auf Platz oder eine Antwort', $r['waiting']);
    if ($r['done'])    $parts[] = t('%d Umzüge fertig', $r['done']);
    return $parts;
}

/** Pictures still waiting to be carried, across all running moves. */
function cloud_moves_pending(PDO $pdo): int
{
    if (!cloud_moves_ready($pdo)) {
        return 0;
    }
    return (int) $pdo->query(
        'SELECT COUNT(*) FROM map_cloud_move_items i
           JOIN map_cloud_moves m ON m.id = i.move_id
          WHERE m.status = "running" AND i.status = "waiting"'
    )->fetchColumn();
}
