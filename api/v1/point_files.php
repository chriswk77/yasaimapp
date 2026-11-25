<?php
/**
 * A find's other files in the cloud (includes/point_files.php says why).
 *
 *   POST point_files.php?id=<point>&action=list
 *   POST point_files.php?id=<point>&action=target   name, type, size, mtime, dir
 *   POST point_files.php?id=<point>&action=record   fileid, folderid, name, size, type, dir
 *   POST point_files.php?id=<point>&action=delete   ids (comma-separated)
 *   POST point_files.php?id=<point>&action=way      file
 *   POST point_files.php?id=<point>&action=text     file
 *
 * OWN FINDS, OWN FILES, OWN CLOUD. Every action asks for a find of the
 * caller's own and touches only rows the caller uploaded, in the cloud he
 * uploads to (cloud_uploads_storage). The bytes travel between browser
 * and provider; this answers where to and writes down where they came to
 * lie.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';

// The recorder app's token is for tracks, never for a cloud (cloud.php).
if (token_from_request() !== null) {
    json_error(t('Ein Geräte-Zugang reicht für die Cloud nicht — bitte im Browser anmelden.'), 403);
}

require_once __DIR__ . '/../../includes/point_files.php';

$userId = (int) $me['id'];
if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
if (!cloud_ready($pdo) || !point_files_ready($pdo)) {
    json_error(t('Die Dateien brauchen die Migration 0153 — sie ist noch nicht eingespielt.'), 503);
}

$id = request_id();
$stmt = $pdo->prepare('SELECT * FROM map_points WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$id, $userId]);
$point = $stmt->fetch();
if ($point === false) {
    json_error(t('Punkt nicht gefunden.'), 404);
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

/** One of the caller's rows on this find, or a 404. */
$row = static function () use ($pdo, $userId, $id): array {
    $q = $pdo->prepare('SELECT * FROM map_point_files WHERE id = ? AND user_id = ? AND point_id = ? LIMIT 1');
    $q->execute([(int) ($_POST['file'] ?? 0), $userId, $id]);
    $found = $q->fetch();
    if ($found === false) {
        json_error(t('Die Datei gibt es hier nicht.'), 404);
    }
    return $found;
};

/** The cloud a row lies in — by its provider, not by whatever is connected now. */
$storageOf = static function (array $file) use ($pdo, $userId): array {
    $s = cloud_storage_for($pdo, $userId, (string) $file['provider']);
    if ($s === null) {
        json_error(t('Die Cloud dieser Datei ist nicht mehr verbunden.'), 409);
    }
    return $s;
};

switch ($action) {

    case 'list':
        json_response(['files' => point_files_list($pdo, $userId, $id)]);
        break;

    // Where the browser sends one file: the find's „Dateien" folder, and in
    // it the folder the file was dropped in — made where missing.
    case 'target':
        $s = cloud_uploads_storage($pdo, $userId);
        if ($s === null) {
            json_error(t('Keine Cloud verbunden.'), 409);
        }
        $name = cloud_file_name_clean((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            json_error(t('Der Dateiname fehlt.'));
        }
        $type = (string) ($_POST['type'] ?? '');
        $type = preg_match('~^[a-z]+/[a-z0-9.+-]+$~i', $type) === 1 ? $type : 'application/octet-stream';
        $size = max(0, (int) ($_POST['size'] ?? 0));
        $mtime = (int) ($_POST['mtime'] ?? 0) ?: null;
        $dir = point_files_dir_clean((string) ($_POST['dir'] ?? ''));
        try {
            $home = point_files_home($pdo, $s, $userId, $point);
            $folder = $dir === '' ? $home['folderid']
                : (string) storage_path_ensure($s, $home['folderid'], explode('/', $dir));
            $way = cloud_upload_target($s, $folder, $name, $type, $size, $mtime);
        } catch (RuntimeException $e) {
            json_error(error_for_user($e), 502);
        }
        json_response($way + ['folderid' => $folder, 'dir' => $dir]);
        break;

    // It is up there — written down, asked back at the provider where one
    // call can (beyond pCloud), as the recordings are.
    case 'record':
        $s = cloud_uploads_storage($pdo, $userId);
        if ($s === null) {
            json_error(t('Keine Cloud verbunden.'), 409);
        }
        $fileid = trim((string) ($_POST['fileid'] ?? ''));
        $name   = trim((string) ($_POST['name'] ?? ''));
        if ($fileid === '' || $name === '' || mb_strlen($fileid) > 512
            || ($s['provider'] === 'pcloud' && !ctype_digit($fileid))) {
            json_error(t('fileid und name werden gebraucht.'));
        }
        $folder = trim((string) ($_POST['folderid'] ?? ''));
        $size = (int) ($_POST['size'] ?? 0) ?: null;
        try {
            if ($s['provider'] !== 'pcloud') {
                if ($s['provider'] === 's3' && !str_starts_with($fileid, (string) cloud_home_resolve($pdo, $userId, $s))) {
                    json_error(t('fileid und name werden gebraucht.'));
                }
                $there = storage_file_meta($s, $fileid);
                if ($there === null) {
                    json_error(t('Die Datei ist in der Cloud nicht angekommen.'), 409);
                }
                $name   = $there['name'] !== '' ? $there['name'] : $name;
                $size   = $there['size'] > 0 ? $there['size'] : $size;
                $folder = $there['parent'] !== '' ? $there['parent'] : $folder;
            }
            $home = point_files_home($pdo, $s, $userId, $point);
        } catch (RuntimeException $e) {
            json_error(error_for_user($e), 502);
        }
        $type = (string) ($_POST['type'] ?? '');
        $pdo->prepare('INSERT INTO map_point_files
                        (user_id, point_id, provider, fileid, folderid, home_folderid, home_path, dir, name, size, mime, uploaded_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$userId, $id, $s['provider'], $fileid, $folder === '' ? null : mb_substr($folder, 0, 512),
                       $home['folderid'], $home['path'], point_files_dir_clean((string) ($_POST['dir'] ?? '')),
                       mb_substr($name, 0, 255), $size,
                       preg_match('~^[a-z]+/[a-z0-9.+-]+$~i', $type) === 1 ? mb_substr($type, 0, 100) : null]);
        json_response(['files' => point_files_list($pdo, $userId, $id)]);
        break;

    case 'delete':
        $ids = array_map('intval', explode(',', (string) ($_POST['ids'] ?? '')));
        $q = $pdo->prepare('SELECT DISTINCT provider FROM map_point_files WHERE point_id = ? AND user_id = ?');
        $q->execute([$id, $userId]);
        try {
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $provider) {
                $s = cloud_storage_for($pdo, $userId, (string) $provider);
                if ($s !== null) {
                    point_files_delete($pdo, $s, $userId, $id, $ids);
                }
            }
        } catch (RuntimeException $e) {
            json_error(error_for_user($e), 502);
        }
        json_response(['files' => point_files_list($pdo, $userId, $id)]);
        break;

    case 'way':
        $file = $row();
        try {
            json_response(point_file_way($storageOf($file), $file) + ['name' => (string) $file['name']]);
        } catch (RuntimeException $e) {
            json_error(error_for_user($e), 502);
        }
        break;

    case 'text':
        $file = $row();
        if (point_file_kind((string) $file['name']) !== 'text') {
            json_error(t('Diese Datei ist kein Text.'));
        }
        // The size first, from the row: a text longer than the window holds
        // is said so, not fetched.
        if ((int) ($file['size'] ?? 0) > POINT_FILES_TEXT_BYTES) {
            json_error(t('Die Vorschau zeigt Texte bis %s — diese Datei ist größer. Lade sie herunter.',
                cloud_size(POINT_FILES_TEXT_BYTES)), 413);
        }
        try {
            json_response(['name' => (string) $file['name'], 'text' => point_file_text($storageOf($file), $file)]);
        } catch (RuntimeException $e) {
            json_error(error_for_user($e), 502);
        }
        break;

    default:
        json_error(t('Unbekannte Aktion.'));
}
