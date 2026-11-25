<?php
/**
 * The calibration images' place in the user's own cloud.
 *
 *   POST calibration.php?action=target   folder id, upload URL and token
 *   POST calibration.php?action=remember a preset from the microscope window
 *
 * ONE ACTION, ONE FOLDER: „Kalibrierung", directly under the storage root,
 * beside the genus folders — the "eigenes Verzeichnis" of the call
 * (28.08.2026). The photographed stage micrometers land there for the
 * RECORD: measuring happens in the browser BEFORE the upload, while the
 * picture is still local, so nothing here ever needs to hand a calibration
 * image back out. No map_cloud_files row either — the folder is an archive
 * for the human, and the preset carries everything the code needs (µm per
 * pixel and the image's dimensions, migration 0093).
 *
 * THE FILE NEVER TOUCHES THIS SERVER — upload_max_filesize is 2 MB on this
 * hosting, and a full-resolution micrograph is many times that. Browser to
 * the cloud directly, exactly like the recordings (cloudupload.js); this
 * endpoint only names the door — into whichever cloud the browser may
 * upload to (CLOUD_UPLOAD_PROVIDERS).
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';

/* NOT FOR DEVICE TOKENS (security review, 23.09.2026). The recorder app's
   bearer token never expires and only ever needs api/v1/tracks.php — but
   here it would receive a way into the account's cloud (at pCloud the
   access token itself), which covers
   the whole cloud folder and does not expire either. A lost phone must
   not be a lost cloud. The browser, with its session, is unaffected. */
if (token_from_request() !== null) {
    json_error(t('Ein Geräte-Zugang reicht für die Cloud nicht — bitte im Browser anmelden.'), 403);
}
require_once __DIR__ . '/../../includes/cloud.php';

$userId = (int) $me['id'];

/* DELETING AND RENAMING, FROM THE MICROSCOPE WINDOW
   (04.11.2026: „Gerät löschen, Objektiv löschen … umbenennen doch wieder mit
   rein"). Overwriting an objective is the calibration itself, and the
   sidebar shows the rest. The same steps the settings' Maßstab-Vorlagen
   offer, answered as the page's new lists. Before the cloud question
   below: a name needs no cloud. */
$manage = (string) ($_GET['action'] ?? '');
if (in_array($manage, ['preset_forget', 'device_forget', 'preset_rename', 'device_rename'], true)) {
    require_once __DIR__ . '/../../includes/photos.php';
    $in = request_data();
    $id = (int) ($in['id'] ?? 0);
    $name = (string) ($in['name'] ?? '');
    // An id of 0 would MAKE a device (scale_device_save) — never here.
    $done = $id > 0;
    try {
        if ($done) {
            switch ($manage) {
                case 'preset_forget': $done = photo_scale_forget($pdo, $userId, $id); break;
                case 'device_forget': $done = scale_device_forget($pdo, $userId, $id); break;
                case 'preset_rename': photo_scale_update($pdo, $userId, $id, $name); break;
                case 'device_rename': scale_device_save($pdo, $userId, $id, $name); break;
            }
        }
    } catch (InvalidArgumentException $e) {
        json_error(error_for_user($e));
    }
    if (!$done) {
        json_error(str_starts_with($manage, 'preset') ? t('Diese Vorlage gibt es nicht mehr.') : t('Dieses Gerät gibt es nicht mehr.'), 404);
    }
    json_response(['scales' => photo_scales($pdo, $userId), 'devices' => scale_devices($pdo, $userId)]);
}

/* The cloud the browser may upload into (cloud_uploads_storage) — each
   provider once it is released on the upload list. The answer names it,
   so the browser knows whose door it knocks at and the preset remembers
   where its image lies. */
$s = cloud_uploads_storage($pdo, $userId);
if ($s === null) {
    json_error(t('Keine Cloud verbunden.'), 409);
}

switch ((string) ($_GET['action'] ?? $_POST['action'] ?? '')) {
    /* The file arrives described — name, size, type, its own time — since
       Drive, OneDrive and S3 open their way for exactly that file
       (cloud_upload_target). No previews: a calibration image is only ever
       fetched whole, to measure on its own pixels. */
    case 'target':
        $wanted = cloud_file_name_clean((string) ($_POST['name'] ?? ''));
        $type   = (string) ($_POST['type'] ?? '');
        $type   = preg_match('~^[a-z]+/[a-z0-9.+-]+$~i', $type) === 1 ? $type : 'application/octet-stream';
        if ($wanted === '' && $s['provider'] !== 'pcloud') {
            json_error(t('Der Dateiname fehlt.'));
        }
        try {
            $folder = storage_folder_ensure($s, cloud_home_resolve($pdo, $userId, $s), 'Kalibrierung');
            $way = cloud_upload_target($s, $folder['folderid'], $wanted, $type,
                max(0, (int) ($_POST['size'] ?? 0)), (int) ($_POST['mtime'] ?? 0) ?: null);
        } catch (RuntimeException $e) {
            // 502: not our mistake, the provider could not deliver.
            json_error(error_for_user($e), 502);
        }
        json_response($way + ['folderid' => (string) $folder['folderid']]);
        break;

    /* A PRESET FROM THE MICROSCOPE WINDOW (24.10.2026: the
       objective calibrated where one is, with the stage micrometer under
       the lens). The same rules as the settings form's „scale_calibrate"
       (settings_content.php), which posts a whole page — the window above
       the map cannot: the line measured in the picture's own pixels, the
       true length, the picture's size, and NO PICTURE KEPT, NO PRESET (the
       browser archives it first and sends its id). A name that exists is
       calibrated anew (photo_scale_remember), so „40x" twice is one
       objective measured twice, not two. Answers the account's presets. */
    case 'remember':
        require_once __DIR__ . '/../../includes/photos.php';
        if (!column_exists($pdo, 'map_photo_scales', 'width')) {
            json_error(t('Das braucht die Migration 0093 — sie ist noch nicht eingespielt.'), 503);
        }
        $in = request_data();
        $linePx = (float) ($in['line_px'] ?? 0);
        $length = (float) str_replace(',', '.', (string) ($in['length'] ?? ''));
        $unit   = (string) ($in['unit'] ?? '');
        $width  = (int) ($in['width'] ?? 0);
        $height = (int) ($in['height'] ?? 0);
        $fileid = trim((string) ($in['fileid'] ?? ''));
        $provider = in_array((string) ($in['provider'] ?? ''), ['pcloud', 'gdrive', 'onedrive', 's3'], true)
            ? (string) $in['provider'] : 'pcloud';
        if (!is_finite($length) || $length <= 0 || !in_array($unit, ['1', '1000'], true)) {
            json_error(t('Die wahre Länge der Strecke fehlt, oder sie ist keine Zahl.'));
        }
        if (!is_finite($linePx) || $linePx < 1) {
            json_error(t('Klicke zuerst Anfang und Ende der Strecke im Bild an.'));
        }
        if ($width <= 0 || $width > 100000 || $height <= 0 || $height > 100000) {
            json_error(t('Die Größe des Bildes fehlt. Wähle das Bild bitte noch einmal.'));
        }
        if ($fileid === '' || $fileid === '0' || mb_strlen($fileid) > 512) {
            json_error(t('Das Eichbild ist nicht in der Cloud angekommen. Die Vorlage ist nicht gespeichert.'));
        }
        /* THE DEVICE the window asked for (0183) — one of the account's,
           or a NEW one named in the calibration (04.11.2026:
           „oder ob man ein neues Gerät anlegen möchte"), made in the same
           transaction as its first objective, so a calibration that fails
           leaves no empty device behind. None means the first. */
        $newDevice = trim((string) ($in['new_device'] ?? ''));
        $deviceId = (int) ($in['device'] ?? 0);
        $pdo->beginTransaction();
        try {
            if ($newDevice !== '') {
                $deviceId = scale_device_save($pdo, $userId, 0, $newDevice);
            } elseif ($deviceId <= 0 && scale_devices_ready($pdo)) {
                $deviceId = scale_device_default($pdo, $userId);
            }
            photo_scale_remember($pdo, $userId, (string) ($in['name'] ?? ''),
                $length * (float) $unit / $linePx, $width, $height, $fileid, $provider,
                $deviceId > 0 ? $deviceId : null);
            $pdo->commit();
        } catch (InvalidArgumentException $e) {
            $pdo->rollBack();
            json_error(error_for_user($e));
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        json_response([
            'scales'    => photo_scales($pdo, $userId),
            'devices'   => scale_devices($pdo, $userId),
            'device_id' => $deviceId > 0 ? $deviceId : null,
        ]);
        break;

    default:
        json_error(t('Unbekannte Aktion.'));
}
