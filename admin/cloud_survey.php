<?php
/**
 * A look at what lies in the maintainer's cloud — reading, but for one
 * small write test — and gone again after three days.
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
 * IT READS, WITH ONE EXCEPTION. One listfolder call at pCloud, SELECTs in
 * the database. Nothing of the user's is moved, renamed or deleted here;
 * what the survey finds is mended by code that is written afterwards, with
 * the findings in hand. The token never leaves the server: it goes to
 * pCloud and is in no answer.
 *
 * THE EXCEPTION IS `probe` (22.11.2026: „mach den Schreibtest"). The survey
 * found names with umlauts in two spellings up there — „ü" as one sign
 * (NFC) and as „u" plus two dots (NFD) — and six find folders twice because
 * of it. Which call makes which cannot be read out of a listing. So the
 * probe makes a folder of its OWN in the app's top, „YASAIMAPP-Schreibtest
 * <random>", tries the cases inside it (folders, uploads, renames in both
 * spellings), reports every name byte for byte, and takes its folder away
 * again. It touches nothing it did not make itself.
 *
 * AND `spelling` WITH run=1 (the same day: „Ja, bau Punkt 3") is the door
 * to the job that mends what the probe explained — it renames and lays
 * folders together, as includes/cloud_spelling.php describes. Without
 * `run` it only lists what it would do.
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
 *   curl -H "X-Survey-Key: …" -d user=<id> -d what=probe …/admin/cloud_survey.php
 *   curl -H "X-Survey-Key: …" -d user=<id> -d what=spelling [-d run=1] …/admin/cloud_survey.php
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

/* ---------------------------------------------------------------------------
 * probe — how pCloud treats the two spellings of an umlaut, tried in a
 * folder of the probe's own (the head of this file says why).
 *
 * EVERY NAME COMES BACK AS HEX. The two spellings look alike in any
 * listing; only the bytes tell them apart („c3bc" is the one sign, „75cc88"
 * the letter with its dots). Each step is reported whether it worked or was
 * refused — a refusal („already exists") is an answer too.
 *
 * WHAT IT MADE GOES AGAIN, file by file and folder by folder, with the
 * calls that take one thing each (deletefile, deletefolder — which refuses
 * a folder that is not empty). No recursive delete is spoken here: a wrong
 * id in one of those would be somebody's archive.
 * ------------------------------------------------------------------------- */
if ($what === 'probe') {
    $s = cloud_storage_for($pdo, $userId, 'pcloud');
    if ($s === null) {
        survey_refuse(409, 'This account has no pCloud connected.');
    }
    $host = $s['host'];
    $token = $s['token'];
    $u = ['ü' => ["\u{00FC}", "u\u{0308}"], 'ö' => ["\u{00F6}", "o\u{0308}"], 'ä' => ["\u{00E4}", "a\u{0308}"]];
    $name = static fn(?string $n): ?array => $n === null ? null : ['text' => $n, 'hex' => bin2hex($n)];
    $steps = [];
    $try = static function (string $label, callable $do) use (&$steps): mixed {
        try {
            $r = $do();
            $steps[] = ['step' => $label] + (is_array($r) ? $r : ['value' => $r]);
            return $r;
        } catch (Throwable $e) {
            $steps[] = ['step' => $label, 'refused' => $e->getMessage()];
            return null;
        }
    };
    $folder = static function (int $parent, string $n) use ($host, $token, $name): array {
        $d = pcloud_call($host, 'createfolderifnotexists', ['access_token' => $token, 'folderid' => $parent, 'name' => $n]);
        return ['sent' => $name($n), 'got' => $name((string) ($d['metadata']['name'] ?? '')),
                'id' => (string) ($d['metadata']['folderid'] ?? ''), 'created' => (bool) ($d['created'] ?? false)];
    };
    $upload = static function (int $parent, string $n, bool $replace) use ($host, $token, $name): array {
        $r = pcloud_upload_bytes($host, $token, $parent, 'Schreibtest ' . bin2hex(random_bytes(4)), $n, null, $replace);
        return ['sent' => $name($n), 'got' => $name($r['name']), 'id' => (string) $r['fileid'], 'replace' => $replace];
    };
    $rename = static function (string $fileId, int $parent, string $n) use ($host, $token, $name): array {
        $d = pcloud_call($host, 'renamefile', ['access_token' => $token, 'fileid' => (int) $fileId,
                                               'tofolderid' => $parent, 'toname' => $n]);
        return ['sent' => $name($n), 'got' => $name((string) ($d['metadata']['name'] ?? '')),
                'id' => (string) ($d['metadata']['fileid'] ?? ''),
                'deletedfileid' => isset($d['metadata']['deletedfileid']) ? (string) $d['metadata']['deletedfileid'] : null];
    };
    $listing = static function (int $id) use ($host, $token, $name): array {
        $d = pcloud_call($host, 'listfolder', ['access_token' => $token, 'folderid' => $id]);
        $out = [];
        foreach ((array) ($d['metadata']['contents'] ?? []) as $e) {
            $out[] = ['folder' => (bool) ($e['isfolder'] ?? false),
                      'id' => (string) ($e['isfolder'] ?? false ? $e['folderid'] : $e['fileid']),
                      'name' => $name((string) $e['name'])];
        }
        return $out;
    };

    // A fresh name each time, so the folder is the probe's own — never one that was there.
    $made = pcloud_call($host, 'createfolderifnotexists', ['access_token' => $token, 'folderid' => cloud_root_id($s['connection']),
        'name' => 'YASAIMAPP-Schreibtest ' . bin2hex(random_bytes(6))]);
    if (!($made['created'] ?? false)) {
        survey_refuse(409, 'The probe folder was there already — nothing done.');
    }
    $base = (int) $made['metadata']['folderid'];
    $left = [];
    try {
        // Folders: the one-sign spelling first, then the other, then the first again.
        $a = $try('folder NFC', fn() => $folder($base, 'Pr' . $u['ü'][0] . 'fung'));
        $try('folder NFD, the NFC one exists', fn() => $folder($base, 'Pr' . $u['ü'][1] . 'fung'));
        $try('folder NFC again', fn() => $folder($base, 'Pr' . $u['ü'][0] . 'fung'));
        // And the other way round, in a second name.
        $try('folder NFD first', fn() => $folder($base, 'Gegenpr' . $u['ü'][1] . 'fung'));
        $try('folder NFC, the NFD one exists', fn() => $folder($base, 'Gegenpr' . $u['ü'][0] . 'fung'));

        // Uploads that keep both (renameifexists=1, the pictures' way).
        $f1 = $try('upload NFC', fn() => $upload($base, 'Gr' . $u['ö'][0] . 'sse.txt', false));
        $f2 = $try('upload NFD, the NFC one exists, keep both', fn() => $upload($base, 'Gr' . $u['ö'][1] . 'sse.txt', false));
        // Uploads that replace (renameifexists=0, the sidecars' way).
        $try('upload NFD to be replaced', fn() => $upload($base, 'Ersatz ' . $u['ä'][1] . '.txt', true));
        $try('upload NFC replacing — one file or two?', fn() => $upload($base, 'Ersatz ' . $u['ä'][0] . '.txt', true));

        // Renames: does the name keep its spelling, and does one spelling overwrite the other?
        if (is_array($f1)) {
            $try('rename to NFC', fn() => $rename($f1['id'], $base, 'Umbenannt ' . $u['ü'][0] . '.txt'));
        }
        if (is_array($f2)) {
            $try('rename to NFD, the NFC one exists — replaced?', fn() => $rename($f2['id'], $base, 'Umbenannt ' . $u['ü'][1] . '.txt'));
        }
        if (is_array($f1)) {
            $try('stat of the first file', function () use ($host, $token, $f1, $name) {
                $m = pcloud_call($host, 'stat', ['access_token' => $token, 'fileid' => (int) $f1['id']])['metadata'] ?? [];
                return ['got' => $name((string) ($m['name'] ?? ''))];
            });
            // A move without a name, into the NFC folder: does the name change on the way?
            if (is_array($a)) {
                $try('move without toname', function () use ($host, $token, $f1, $a, $name) {
                    $d = pcloud_call($host, 'renamefile', ['access_token' => $token, 'fileid' => (int) $f1['id'], 'tofolderid' => (int) $a['id']]);
                    return ['got' => $name((string) ($d['metadata']['name'] ?? ''))];
                });
            }
        }
        $steps[] = ['step' => 'listing at the end', 'entries' => $listing($base)];
    } finally {
        // Away again: what lies in the probe's folder, one storey deep, then the folder.
        try {
            foreach ($listing($base) as $e) {
                if ($e['folder']) {
                    foreach ($listing((int) $e['id']) as $inner) {
                        pcloud_call($host, $inner['folder'] ? 'deletefolder' : 'deletefile',
                            ['access_token' => $token, ($inner['folder'] ? 'folderid' : 'fileid') => (int) $inner['id']]);
                    }
                    pcloud_call($host, 'deletefolder', ['access_token' => $token, 'folderid' => (int) $e['id']]);
                } else {
                    pcloud_call($host, 'deletefile', ['access_token' => $token, 'fileid' => (int) $e['id']]);
                }
            }
            pcloud_call($host, 'deletefolder', ['access_token' => $token, 'folderid' => $base]);
        } catch (Throwable $e) {
            $left = ['folderid' => (string) $base, 'why' => $e->getMessage()];
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'at' => date('c'), 'steps' => $steps, 'left_behind' => $left,
        'php' => PHP_VERSION, 'sapi' => PHP_SAPI, 'normalizer' => class_exists('Normalizer'),
        'mb_internal' => mb_internal_encoding(), 'default_charset' => ini_get('default_charset'),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/* ---------------------------------------------------------------------------
 * spelling — the one-time job that brings this account's pCloud to one
 * spelling of its umlauts and lays the twin folders together
 * (includes/cloud_spelling.php tells the whole of it).
 *
 * WITHOUT `run` IT IS THE LIST: every step the job would take, and nothing
 * is touched — what the maintainer was shown before anything ran („vorher
 * bekommst du die Liste"). With run=1 the steps are done, as far as the
 * time of one call reaches; the answer says how many are still open, and
 * the next call lists again and goes on.
 * ------------------------------------------------------------------------- */
if ($what === 'spelling') {
    require_once __DIR__ . '/../includes/cloud_spelling.php';
    $s = cloud_storage_for($pdo, $userId, 'pcloud');
    if ($s === null) {
        survey_refuse(409, 'This account has no pCloud connected.');
    }
    $run = ($_POST['run'] ?? '') === '1';
    try {
        $r = cloud_spelling_account($pdo, $userId, $s, microtime(true) + 80, !$run);
    } catch (Throwable $e) {
        survey_refuse(502, $e->getMessage());
    }
    header('Content-Type: application/json; charset=utf-8');
    ob_start('ob_gzhandler');
    echo json_encode(['at' => date('c'), 'ran' => $run] + $r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/* accounts — how many connections each provider has here, as a number and nothing else: whether another pCloud may hold names from before. */
if ($what === 'accounts') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($pdo->query('SELECT provider, COUNT(*) AS n FROM map_cloud_accounts GROUP BY provider')->fetchAll());
    exit;
}

survey_refuse(400, 'what = tree | rows | log | probe | spelling | accounts');
