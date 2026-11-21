<?php
/**
 * A logo for the watermark templates, uploaded (Einstellungen → Fotos →
 * Export, assets/js/exportsettings.js).
 *
 *   POST export_logo.php   multipart 'logo' — a PNG the browser made
 *                          → {file: <hash>}
 *
 * THE BROWSER HANDS IN A PNG IT DREW ITSELF: whatever the person picked (a
 * JPEG, a PNG with transparency, a WebP) is drawn onto a canvas at most
 * LOGO_EDGE wide and sent as PNG. So the server only has to check that it is
 * a PNG of a sane size — no format zoo, no SVG with scripts in it.
 *
 * STORED ON THE SERVER, in data/users/<id>/logos/, not in the cloud: it is
 * the account's mark, a few kilobytes, read by every export and every
 * preview; a cloud round trip each time would be the wrong price. Under the
 * hash of its bytes, so the same logo twice is one file. A template points
 * at it by that hash; logos no template uses go after the next save
 * (export_logos_tidy).
 */

declare(strict_types=1);
require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/user_store.php';
require_once __DIR__ . '/../../includes/export_style.php';

const LOGO_EDGE = 1024;
const LOGO_BYTES = 2 * 1024 * 1024;

if ($method !== 'POST') {
    json_error(t('Methode nicht unterstützt.'), 405);
}
$upload = $_FILES['logo'] ?? null;
if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
    json_error(t('Das Logo kam nicht an.'), 422);
}
if ((int) $upload['size'] <= 0 || (int) $upload['size'] > LOGO_BYTES) {
    json_error(t('Das Logo ist zu groß.'), 422);
}
$info = @getimagesize($upload['tmp_name']);
if ($info === false || ($info[2] ?? 0) !== IMAGETYPE_PNG || $info[0] > LOGO_EDGE || $info[1] > LOGO_EDGE) {
    json_error(t('Das Logo ließ sich nicht lesen.'), 422);
}
$userId = (int) $me['id'];
$dir = export_logo_directory($userId);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    json_error(t('Das Logo ließ sich nicht speichern.'), 500);
}
// The same block as every user directory gets (user_photo_directory_create).
$lock = user_root() . '/.htaccess';
if (!is_file($lock)) {
    file_put_contents($lock, "Deny from all\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n");
}
$hash = sha1_file($upload['tmp_name']);
$target = export_logo_path($userId, $hash);
if (!is_file($target) && !move_uploaded_file($upload['tmp_name'], $target)) {
    json_error(t('Das Logo ließ sich nicht speichern.'), 500);
}
// Touched, so the tidying after the next save gives it its hour.
@touch($target);
json_response(['file' => $hash]);
