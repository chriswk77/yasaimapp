<?php
/**
 * A look at what lies in the maintainer's cloud — read-only, and gone again
 * after three days.
 *
 * WHY THIS FILE EXISTS (22.11.2026). After the folders were rebuilt
 * („Arten/" and „Listen/", 18.11.2026) the move stopped half way: old
 * folders still stand, sidecar files hang on old names, and broken uploads
 * from the woods lie beside the picture that finally arrived. Naming every
 * such file by hand would take longer than reading the whole tree once
 * („schau dir meine Cloud an, hol dir die Statistik zurück"). So this page
 * hands back two things to compare: what the provider says is up there, and
 * what the database believes — with the name and the folder the naming
 * worker (includes/cloud_names.php) wants for every picture.
 *
 * IT READS AND NOTHING ELSE. One listfolder call at pCloud, SELECTs in the
 * database. Nothing is moved, renamed or deleted here; what the survey finds
 * is mended by code that is written afterwards, with the findings in hand.
 * The token never leaves the server: it goes to pCloud and is in no answer.
 *
 * WHO MAY CALL IT. No login — the one who reads the answer is a script on
 * the maintainer's own machine. A key comes as a header (never in the
 * address, where every access log would keep it); the file knows only the
 * key's SHA-256. And only an administrator's own pCloud can be asked for.
 *
 * IT SWITCHES ITSELF OFF. The deployment never deletes, so a file taken out
 * of the repository stays on the server; SURVEY_UNTIL makes that copy
 * harmless by itself. Remove the file from the repository when the hunt is
 * over, and from the server by hand some day.
 *
 *   curl -H "X-Survey-Key: …" -d user=<id> -d what=rows  …/admin/cloud_survey.php
 *   curl -H "X-Survey-Key: …" -d user=<id> -d what=tree  …/admin/cloud_survey.php
 *   curl -H "X-Survey-Key: …" -d user=<id> -d what=log   …/admin/cloud_survey.php
 */

declare(strict_types=1);

const SURVEY_KEY_SHA256 = 'dba7ee137dd64549d228a834fa7f210c891e4137114c23787195f6b0265695be';
const SURVEY_UNTIL      = '2026-11-26';

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cloud.php';
require_once __DIR__ . '/../includes/cloud_names.php';
require_once __DIR__ . '/../includes/photos.php';
require_once __DIR__ . '/../includes/photo_cloud.php';

/** End with a status and one plain line. */
function survey_refuse(int $status, string $why): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($why . "\n");
}

// A copy left on a server answers as if it were not there.
if (date('Y-m-d') > SURVEY_UNTIL) {
    survey_refuse(404, 'Not found.');
}
// hash_equals, not ===: a comparison that gives up at the first wrong byte
// tells how many bytes were right.
$sent = (string) ($_SERVER['HTTP_X_SURVEY_KEY'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $sent === ''
    || !hash_equals(SURVEY_KEY_SHA256, hash('sha256', $sent))) {
    survey_refuse(404, 'Not found.');
}

$userId = (int) ($_POST['user'] ?? 0);
$role = $pdo->prepare('SELECT role FROM map_users WHERE id = ?');
$role->execute([$userId]);
if ($role->fetchColumn() !== 'admin') {
    survey_refuse(403, 'Only an administrator\'s own cloud can be surveyed.');
}

@set_time_limit(110);
// Nobody is logged in here; the session bootstrap opened must not hold a lock for a minute.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$what = (string) ($_POST['what'] ?? '');

/* ---------------------------------------------------------------------------
 * tree — everything in the app's folder at pCloud, as the provider says it.
 *
 * PASSED THROUGH, NOT DECODED. One recursive listfolder answers with the
 * whole tree; decoded here, some ten thousand entries would cost a few
 * hundred megabytes of arrays. The answer holds names, sizes, ids and
 * times — no token — so it is copied to the caller as it comes, gzipped on
 * the way, and taken apart on the other side.
 * ------------------------------------------------------------------------- */
if ($what === 'tree') {
    $s = cloud_storage_for($pdo, $userId, 'pcloud');
    if ($s === null) {
        survey_refuse(409, 'This account has no pCloud connected.');
    }
    $in = @fopen('https://' . $s['host'] . '/listfolder', 'r', false, stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => http_build_query([
                'access_token' => $s['token'],
                'folderid'     => cloud_root_id($s['connection']),
                'recursive'    => 1,
                'timeformat'   => 'timestamp',
            ]),
            'timeout'       => 100,
            'ignore_errors' => true,
        ],
    ]));
    if ($in === false) {
        survey_refuse(502, 'pCloud does not answer.');
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Encoding: gzip');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $out = fopen('php://output', 'w');
    stream_filter_append($out, 'zlib.deflate', STREAM_FILTER_WRITE, ['window' => 31]);
    stream_copy_to_stream($in, $out);
    fclose($out);
    exit;
}

/* ---------------------------------------------------------------------------
 * rows — what the database believes lies in this account's cloud, and what
 * the naming worker wants for each picture.
 *
 * THE WANTED NAMES COME FROM THE WORKER'S OWN FUNCTIONS (gallery order,
 * stems, places), read the way cloud_names_point() reads them — but only
 * the reading half: no storage is opened, nothing is touched. A copy of
 * that half and not a shared function, because this file goes again and
 * the worker should not be reshaped for a visitor.
 * ------------------------------------------------------------------------- */
if ($what === 'rows') {
    $out = ['at' => date('c'), 'user' => $userId, 'points' => [], 'due' => [], 'account' => null];

    $q = $pdo->prepare('SELECT provider, region, folder, home_folder, home_folder_id, created_at, checked_at
                          FROM map_cloud_accounts WHERE user_id = ?');
    $q->execute([$userId]);
    $out['account'] = $q->fetchAll();

    // Every find with a picture in THIS account's cloud: own recordings, own voucher photos, copies held as co-owner.
    $ids = [];
    $q = $pdo->prepare('SELECT DISTINCT point_id FROM map_cloud_files WHERE user_id = ?');
    $q->execute([$userId]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) $ids[(int) $id] = true;
    $q = $pdo->prepare('SELECT DISTINCT point_id FROM map_photos WHERE user_id = ? AND cloud_fileid IS NOT NULL AND cloud_fileid <> ""');
    $q->execute([$userId]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) $ids[(int) $id] = true;
    $q = $pdo->prepare('SELECT origin_kind, origin_id FROM map_cloud_copies WHERE user_id = ?');
    $q->execute([$userId]);
    foreach ($q->fetchAll() as $copy) {
        $t = $copy['origin_kind'] === 'photo' ? 'map_photos' : 'map_cloud_files';
        $p = $pdo->prepare("SELECT point_id FROM $t WHERE id = ?");
        $p->execute([(int) $copy['origin_id']]);
        $id = $p->fetchColumn();
        if ($id !== false) $ids[(int) $id] = true;
    }

    $styles = [];
    foreach (array_keys($ids) as $pointId) {
        $q = $pdo->prepare('SELECT * FROM map_points WHERE id = ? LIMIT 1');
        $q->execute([$pointId]);
        $point = $q->fetch();
        if ($point === false) {
            $out['points'][] = ['id' => $pointId, 'gone' => true];
            continue;
        }
        $pictures = [];
        $q = $pdo->prepare('SELECT * FROM map_photos WHERE point_id = ?');
        $q->execute([$pointId]);
        foreach ($q as $r) $pictures[] = ['kind' => 'photo', 'row' => $r];
        $q = $pdo->prepare('SELECT * FROM map_cloud_files WHERE point_id = ?');
        $q->execute([$pointId]);
        foreach ($q as $r) $pictures[] = ['kind' => 'cloud', 'row' => $r];

        $holder = (int) $point['user_id'];
        if (!isset($styles[$holder])) {
            $h = $pdo->prepare('SELECT measure_style FROM map_users WHERE id = ?');
            $h->execute([$holder]);
            $styles[$holder] = measure_style_read($h->fetchColumn() ?: null)['photo_titles'] ?? [];
        }
        $pictures = cloud_names_gallery_order($pictures, $styles[$holder]);
        $stems = cloud_name_stems(
            cloud_name_head(cloud_name_species($pdo, $point), $point),
            array_map(static fn(array $p): ?string => photo_title_clean($p['row']['title'] ?? null), $pictures),
            array_map(static fn(array $p): bool => cloud_name_stacked($p['row']['original_name'] ?? null), $pictures)
        );
        [$named, $loose] = cloud_names_places($pdo, $userId, $point);

        $items = [];
        foreach ($pictures as $i => $p) {
            $r = $p['row'];
            $photo = $p['kind'] === 'photo';
            $mine = (int) $r['user_id'] === $userId;
            $fileid = (string) ($photo ? ($r['cloud_fileid'] ?? '') : ($r['fileid'] ?? ''));
            if ($mine) {
                $items[] = [
                    'kind' => $p['kind'], 'id' => (int) $r['id'], 'nr' => $i + 1, 'stem' => $stems[$i],
                    'provider' => (string) ($photo ? ($r['cloud_provider'] ?? '') : ($r['provider'] ?? '')),
                    'fileid' => $fileid,
                    'folder' => (string) ($photo ? ($r['cloud_folderid'] ?? '') : ($r['folderid'] ?? '')),
                    'path' => (string) ($photo ? ($r['cloud_path'] ?? '') : ($r['path'] ?? '')),
                    'name' => $photo ? ($r['cloud_name'] ?? null) : ($r['name'] ?? null),
                    'original' => $r['original_name'] ?? null,
                    'server_file' => $photo ? ($r['filename'] ?? null) : null,
                    'title' => $r['title'] ?? null,
                    'size' => (int) ($photo ? ($r['bytes'] ?? 0) : ($r['size'] ?? 0)),
                    'uploaded' => $photo ? ($r['cloud_uploaded_at'] ?? null) : ($r['uploaded_at'] ?? null),
                    'created' => $photo ? ($r['created_at'] ?? null) : null,
                    'has_scale' => ($r['scale'] ?? '') !== '' && $r['scale'] !== null,
                    'has_measures' => ($r['measures'] ?? '') !== '' && $r['measures'] !== null,
                    'has_develop' => ($r['develop'] ?? '') !== '' && $r['develop'] !== null,
                ];
            }
            $c = $pdo->prepare('SELECT * FROM map_cloud_copies WHERE origin_kind = ? AND origin_id = ? AND user_id = ?');
            $c->execute([$p['kind'], (int) $r['id'], $userId]);
            foreach ($c as $copy) {
                $items[] = [
                    'kind' => 'copy', 'id' => (int) $copy['id'], 'nr' => $i + 1, 'stem' => $stems[$i],
                    'provider' => (string) $copy['provider'], 'fileid' => (string) ($copy['fileid'] ?? ''),
                    'folder' => (string) ($copy['folderid'] ?? ''), 'path' => (string) ($copy['path'] ?? ''),
                    'name' => $copy['name'] ?? null, 'size' => (int) ($copy['size'] ?? 0),
                    'status' => $copy['status'], 'attempts' => (int) $copy['attempts'], 'error' => $copy['error'],
                    'uploaded' => $copy['copied_at'] ?? null, 'origin' => $p['kind'] . ':' . (int) $r['id'],
                ];
            }
        }
        $out['points'][] = [
            'id' => $pointId, 'holder' => $holder, 'title' => $point['title'],
            'found_at' => $point['found_at'], 'created_at' => $point['created_at'], 'updated_at' => $point['updated_at'],
            'deleted_at' => $point['deleted_at'], 'find_number' => $point['find_number'],
            'municipality' => $point['municipality'], 'region' => $point['region'], 'country' => $point['country'],
            'pictures' => count($pictures), 'named' => $named, 'loose' => $loose, 'items' => $items,
        ];
    }

    if (table_exists($pdo, 'map_cloud_names_due')) {
        $out['due'] = $pdo->query('SELECT d.point_id, d.due_at, d.claimed_at, d.attempts, p.user_id
                                     FROM map_cloud_names_due d LEFT JOIN map_points p ON p.id = d.point_id')->fetchAll();
    }

    header('Content-Type: application/json; charset=utf-8');
    ob_start('ob_gzhandler');
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/* ---------------------------------------------------------------------------
 * log — what the naming worker and its neighbours wrote into PHP's error
 * log, where the server lets this page read it. Only lines that speak of
 * the cloud, and only the tail: the log is the host's, not ours.
 * ------------------------------------------------------------------------- */
if ($what === 'log') {
    header('Content-Type: text/plain; charset=utf-8');
    $file = (string) ini_get('error_log');
    if ($file === '' || !is_file($file) || !is_readable($file)) {
        exit('error_log: ' . ($file === '' ? '(not set — the web server keeps it)' : basename($file) . ' is not readable from here') . "\n");
    }
    $size = (int) filesize($file);
    $h = fopen($file, 'r');
    fseek($h, max(0, $size - 4 * 1024 * 1024));
    $lines = 0;
    ob_start('ob_gzhandler');
    echo 'error_log: ', $size, " bytes, the last 4 MB searched\n";
    while (($line = fgets($h)) !== false) {
        if (preg_match('/cloud|pcloud|point_files|storage_/i', $line) === 1) {
            echo $line;
            $lines++;
        }
    }
    echo $lines, " line(s)\n";
    exit;
}

survey_refuse(400, 'what = tree | rows | log');
