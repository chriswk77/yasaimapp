<?php
/**
 * A watermark template's logo, for its owner only (includes/export_style.php).
 *
 *   GET pages/export_logo.php?file=<hash>
 *
 * The logo is the account's own mark, drawn into the pictures it exports and
 * into the settings tab's preview — nobody else's page ever asks for it, so
 * nobody else gets it. The file name is the hash of its bytes: the same
 * address always means the same picture, and it may be kept for a year.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/user_store.php';
require_once __DIR__ . '/../includes/export_style.php';

$me = login_required();
session_write_close();

$path = export_logo_path((int) $me['id'], (string) ($_GET['file'] ?? ''));
if ($path === null || !is_file($path)) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/png');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
readfile($path);
