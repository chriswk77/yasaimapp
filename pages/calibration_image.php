<?php
/**
 * Deliver a preset's calibration image — through this server.
 *
 *   calibration_image.php?preset=7   the stored picture, untouched
 *
 * For re-measuring in the settings' calibration stage
 * (28.08.2026: „nochmal neu nacheichen mit dem gleichen Bild natürlich").
 * The preset knows its picture by the provider's fileid (migration 0094)
 * — never by name: renameifexists turns re-uploads into „10x (2).jpg",
 * and guessing which of those is current would be exactly that.
 *
 * THE DETOUR THROUGH THIS SERVER IS THE SAME NECESSITY as in
 * cloud_thumb.php: pCloud's links are bound to the IP that asked for
 * them, so whoever asks for the link has to be the one who fetches the
 * picture. Session cookie only, like every <img src> route.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cloud.php';

$me = login_required();
$userId = (int) $me['id'];

$presetId = (int) ($_GET['preset'] ?? 0);
if ($presetId <= 0 || !table_exists($pdo, 'map_photo_scales')
    || !column_exists($pdo, 'map_photo_scales', 'fileid')) {
    http_response_code(404);
    exit;
}

// Not present, not mine and without a stored image all read the same from
// the outside — anything else would say which ids are taken.
/* The image lies where it was put — the provider beside its id (0138);
   before that column there was only pCloud. */
$hasProvider = column_exists($pdo, 'map_photo_scales', 'provider');
$stmt = $pdo->prepare(
    'SELECT fileid' . ($hasProvider ? ', provider' : '')
    . ' FROM map_photo_scales WHERE id = ? AND user_id = ? LIMIT 1'
);
$stmt->execute([$presetId, $userId]);
$row    = $stmt->fetch();
$fileid = $row === false ? '' : (string) ($row['fileid'] ?? '');
if ($fileid === '') {
    http_response_code(404);
    exit;
}

$s = cloud_storage_for($pdo, $userId, (string) ($row['provider'] ?? '') ?: 'pcloud');
if ($s === null) {
    http_response_code(404);
    exit;
}

// The file itself, never a preview: measuring needs the file's own pixel
// grid — a rendering would lie to the calibration by its shrink factor.
try {
    $candidates = storage_file_fetch($s, $fileid);
} catch (RuntimeException $e) {
    error_log('calibration_image (' . $fileid . '): ' . $e->getMessage());
    $candidates = [];
}

if ($candidates === []) {
    http_response_code(404);
    exit;
}

if (!cloud_deliver($candidates, 'calibration_image ' . $presetId)) {
    http_response_code(502);
}
