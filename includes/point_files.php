<?php
/**
 * A FIND'S OTHER FILES — notes, PDFs, a stack's raw files — in the cloud
 * beside its pictures (05.10.2026).
 *
 * WHY. Finn asked whether he may lay his own files into a find's folder in
 * Google Drive. He may, but YASAIMAPP never sees them there: Drive shows an
 * app only what it put in itself (the drive.file scope). So when a find
 * changed folders — named after the microscope, renumbered — his files
 * stayed behind in the old one. The answer: a sub-tab „Dateien"
 * under „Belege" with a place to drop files and whole folders, „dadurch
 * würden wir ja die Datei-IDs bekommen … bei einem Umzug käme alles mit",
 * and a small file browser with download and, where it goes, a preview.
 *
 * WHERE THEY LIE: in a folder „Dateien" inside the find's folder, one per
 * find, person and cloud, and below it the folders as they were dropped
 * („Stack 1/RAW/…"). The pictures stay among themselves, their numbers and
 * names never meet a file's, and when the find moves, ONE folder moves
 * (point_files_follow, called by the names worker).
 *
 * WHO SEES THEM: whoever uploaded them, nobody else (his answer). They lie
 * in his cloud and are his working material; a co-owner sees his own.
 *
 * THE BYTES NEVER PASS THIS SERVER, up or down — a raw set is hundreds of
 * megabytes, and the host takes two through a browser's upload. Up they go
 * the way the recordings do (cloud_upload_target, cloudsend.js); down by
 * the provider's own address (point_file_way). Only a preview — a picture's
 * thumbnail, the first half megabyte of a text — comes through here.
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud.php';

/** The folder in the find's folder that holds them. */
const POINT_FILES_FOLDER = 'Dateien';

/** A text shown in the window, at most — a note, not a log of a year. */
const POINT_FILES_TEXT_BYTES = 512 * 1024;

/** Folders inside folders, as dropped — a sane depth, not a file system's. */
const POINT_FILES_DEPTH = 8;

function point_files_ready(PDO $pdo): bool
{
    static $ready = null;
    return $ready ??= table_exists($pdo, 'map_point_files');
}

/**
 * How the tab shows a file: a picture's thumbnail, a text to read, a PDF to
 * open — everything else by its name, for download. By the ending, because
 * that is what the person chose; the browser's type for a raw file is
 * usually empty.
 */
function point_file_kind(string $name): string
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return match (true) {
        in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) => 'image',
        in_array($ext, ['txt', 'md', 'csv', 'tsv', 'log', 'json', 'xml', 'gpx', 'kml',
                        'fasta', 'fas', 'fa', 'yaml', 'yml', 'ini'], true) => 'text',
        $ext === 'pdf' => 'pdf',
        default => 'other',
    };
}

/** A dropped folder's path, cleaned segment by segment — '' for the top. */
function point_files_dir_clean(string $raw): string
{
    $parts = [];
    foreach (preg_split('~[/\\\\]+~u', $raw) ?: [] as $segment) {
        $clean = cloud_file_name_clean($segment);
        if ($clean === '' || $clean === '.' || $clean === '..') {
            continue;
        }
        $parts[] = mb_substr($clean, 0, 100);
    }
    return implode('/', array_slice($parts, 0, POINT_FILES_DEPTH));
}

/**
 * The person's files on a find, for the tab — folders first by their path,
 * then the names.
 *
 * @return list<array{id: int, dir: string, name: string, size: ?int, kind: string, provider: string, uploaded_at: string}>
 */
function point_files_list(PDO $pdo, int $userId, int $pointId): array
{
    if (!point_files_ready($pdo)) {
        return [];
    }
    $q = $pdo->prepare('SELECT id, dir, name, size, provider, uploaded_at FROM map_point_files
                         WHERE point_id = ? AND user_id = ? ORDER BY dir, name');
    $q->execute([$pointId, $userId]);
    return array_map(static fn(array $r): array => [
        'id'          => (int) $r['id'],
        'dir'         => (string) $r['dir'],
        'name'        => (string) $r['name'],
        'size'        => $r['size'] === null ? null : (int) $r['size'],
        'kind'        => point_file_kind((string) $r['name']),
        'provider'    => (string) $r['provider'],
        'uploaded_at' => (string) $r['uploaded_at'],
    ], $q->fetchAll());
}

/**
 * The find's „Dateien" folder in this cloud: the one its files already lie
 * in, or a new one in the find's folder. A folder the person deleted by
 * hand is made anew where the find lies now.
 *
 * @return array{folderid: string, path: string}
 */
function point_files_home(PDO $pdo, array $s, int $userId, array $point): array
{
    $q = $pdo->prepare('SELECT home_folderid, home_path FROM map_point_files
                         WHERE point_id = ? AND user_id = ? AND provider = ? ORDER BY id DESC LIMIT 1');
    $q->execute([(int) $point['id'], $userId, $s['provider']]);
    $known = $q->fetch();
    // S3 has no folders to lose — a prefix is there as long as it is named.
    if ($known !== false && ($s['provider'] === 's3'
            || storage_folder_state($s, (string) $known['home_folderid']) !== null)) {
        return ['folderid' => (string) $known['home_folderid'], 'path' => (string) $known['home_path']];
    }
    $root   = cloud_home_resolve($pdo, $userId, $s);
    $target = cloud_target_folder($s, $root, $pdo, $userId, $point);
    $made   = storage_folder_ensure($s, $target['folderid'], POINT_FILES_FOLDER);
    $path   = mb_substr(implode('/', $target['path']) . '/' . POINT_FILES_FOLDER, 0, 512);
    if ($known !== false) {
        // The old folder is gone — the rows that pointed there follow the new one.
        $pdo->prepare('UPDATE map_point_files SET home_folderid = ?, home_path = ?
                        WHERE point_id = ? AND user_id = ? AND provider = ?')
            ->execute([(string) $made['folderid'], $path, (int) $point['id'], $userId, $s['provider']]);
    }
    return ['folderid' => (string) $made['folderid'], 'path' => $path];
}

/**
 * THE FILES FOLLOW THEIR FIND — the names worker calls this for every find
 * it looks at (cloud_names_point), so a find that changed folders takes its
 * „Dateien" folder along, whole, with everything dropped into it. The same
 * rule as for the pictures (cloud_names_moves): a find that lost its name
 * for a moment keeps them where the last name put them. Decided from the
 * rows alone; the cloud is opened only when something moves.
 *
 * @return array{moved: int, failed: int}
 */
function point_files_follow(PDO $pdo, array $point, ?callable $open = null): array
{
    $out = ['moved' => 0, 'failed' => 0];
    if (!point_files_ready($pdo)) {
        return $out;
    }
    require_once __DIR__ . '/cloud_names.php';
    require_once __DIR__ . '/photo_cloud.php';   // cloud_find_path, which cloud_names_places asks
    $q = $pdo->prepare('SELECT DISTINCT user_id, provider, home_folderid, home_path FROM map_point_files WHERE point_id = ?');
    $q->execute([(int) $point['id']]);
    foreach ($q->fetchAll() as $home) {
        $user = (int) $home['user_id'];
        $findPath = (string) preg_replace('~/' . preg_quote(POINT_FILES_FOLDER, '~') . '$~u', '', (string) $home['home_path']);
        [$named, $loose] = cloud_names_places($pdo, $user, $point);
        if (!cloud_names_moves(['path' => $findPath], $named, $loose)) {
            continue;
        }
        $s = $open !== null ? $open($user, (string) $home['provider'])
                            : cloud_storage_for($pdo, $user, (string) $home['provider']);
        if ($s === null) {
            continue;
        }
        try {
            $root = cloud_home_resolve($pdo, $user, $s);
            if ($named !== null) {
                $t = cloud_target_folder($s, $root, $pdo, $user, $point);
                [$toId, $toPath] = [$t['folderid'], implode('/', $t['path'])];
            } else {
                $parts = cloud_undetermined_parts($point, cloud_find_number($point), $pdo);
                [$toId, $toPath] = [storage_path_ensure($s, $root, $parts), implode('/', $parts)];
            }
            // Where it lay, to tidy up behind it: a find folder left with
            // nothing in it goes, as behind the pictures (storage_prune_empty —
            // never the app's own folder, nothing at Drive).
            $before = storage_folder_state($s, (string) $home['home_folderid'])['parent'] ?? '';
            storage_folder_move($s, (string) $home['home_folderid'], $toId);
            if ($before !== '') {
                storage_prune_empty($pdo, $user, $s, $before);
            }
            $pdo->prepare('UPDATE map_point_files SET home_path = ?
                            WHERE point_id = ? AND user_id = ? AND provider = ? AND home_folderid = ?')
                ->execute([mb_substr($toPath . '/' . POINT_FILES_FOLDER, 0, 512), (int) $point['id'], $user,
                           (string) $home['provider'], (string) $home['home_folderid']]);
            $out['moved']++;
        } catch (RuntimeException $e) {
            error_log('point_files_follow (' . (int) $point['id'] . '): ' . $e->getMessage());
            $out['failed']++;
        }
    }
    return $out;
}

/**
 * Delete files — from the cloud (into its trash where it has one; S3
 * deletes for good) and from the list. Folders left empty go too, up to
 * the find's folder (storage_prune_empty, which never touches the app's
 * own folder and does nothing at Drive, where it cannot see what else
 * lies in a folder).
 *
 * @param list<int> $ids
 * @return int how many went
 */
function point_files_delete(PDO $pdo, array $s, int $userId, int $pointId, array $ids): int
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $i): bool => $i > 0)));
    if ($ids === []) {
        return 0;
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT * FROM map_point_files WHERE id IN ($marks) AND user_id = ? AND point_id = ? AND provider = ?");
    $q->execute([...$ids, $userId, $pointId, $s['provider']]);
    $gone = 0;
    $folders = [];
    foreach ($q->fetchAll() as $row) {
        storage_file_delete($s, (string) $row['fileid']);
        $pdo->prepare('DELETE FROM map_point_files WHERE id = ?')->execute([(int) $row['id']]);
        $gone++;
        if ((string) ($row['folderid'] ?? '') !== '') {
            $folders[(string) $row['folderid']] = true;
        }
    }
    foreach (array_keys($folders) as $folder) {
        $still = $pdo->prepare('SELECT COUNT(*) FROM map_point_files WHERE folderid = ? AND user_id = ?');
        $still->execute([$folder, $userId]);
        if ((int) $still->fetchColumn() === 0) {
            try {
                storage_prune_empty($pdo, $userId, $s, $folder);
            } catch (RuntimeException $e) {
                error_log('point_files_delete prune: ' . $e->getMessage());
            }
        }
    }
    return $gone;
}

/**
 * How the browser reaches a file — never through this server.
 *
 * PCLOUD'S LINK THE BROWSER ASKS FOR ITSELF: pCloud makes its links out to
 * the IP that asked (pcloud_deliver says so), so one fetched here would be
 * refused over there. The browser gets what it needs to ask — the same
 * access token, fenced into the app's folder, that it already receives to
 * upload (api/v1/cloud.php explains why that is bearable), and only for a
 * file of its own. OneDrive and S3 hand out an address that carries its
 * own permission. Drive's content wants a token in a header no link can
 * carry, so Drive opens the file in Drive, where its owner is signed in.
 *
 * @return array{kind: string, url: string, token?: string, fileid?: string}
 */
function point_file_way(array $s, array $row): array
{
    $fileid = (string) $row['fileid'];
    switch ($s['provider']) {
        case 'onedrive':
            $at = onedrive_file_fetch($s['token'], $fileid);
            if ($at === null) {
                throw new RuntimeException(t('Die Datei liegt nicht mehr in deiner Cloud.'));
            }
            return ['kind' => 'url', 'url' => $at['url']];
        case 's3':
            return ['kind' => 'url', 'url' => s3_presigned($s['token'], 'GET', $fileid, 900)];
        case 'gdrive':
            $d = gdrive_call($s['token'], 'GET', GDRIVE_API . '/files/' . rawurlencode($fileid) . '?fields=webViewLink');
            return ['kind' => 'drive', 'url' => (string) ($d['webViewLink'] ?? '')];
        default:
            return ['kind' => 'pcloud', 'url' => 'https://' . $s['host'] . '/getfilelink',
                    'token' => $s['token'], 'fileid' => $fileid];
    }
}

/** A text file's beginning, as readable text — for the preview window. */
function point_file_text(array $s, array $row): string
{
    $bytes = storage_file_bytes($s, (string) $row['fileid'], POINT_FILES_TEXT_BYTES);
    // Notes written on Windows years ago are rarely UTF-8.
    return mb_check_encoding($bytes, 'UTF-8') ? $bytes : (string) mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
}
